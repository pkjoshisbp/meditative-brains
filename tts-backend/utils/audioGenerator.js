import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import axios from 'axios';
import logger from './logger.js';
import slugify from 'slugify'; // If not installed, run: npm install slugify
import { parseSSMLForVITS, enhanceTextForVITS } from './ssmlParser.js';

// Load voices list once at startup
import voicesList from '../azure-voices.json' assert { type: 'json' };

const AUDIO_CACHE_BASE = path.join(process.cwd(), 'audio-cache');
const PRODUCTS_AUDIO_BASE = path.join(process.cwd(), 'products-audio');
const TEMP_TEXT_DIR = path.join(process.cwd(), 'temp-texts');

// Create necessary directories
for (const dir of [AUDIO_CACHE_BASE, PRODUCTS_AUDIO_BASE, TEMP_TEXT_DIR]) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

function hashMessage(text) {
  return crypto.createHash('md5').update(text).digest('hex'); // Fixed missing parenthesis
}

function slugifyText(text, maxLength = 40) { // Reduce maxLength to 40 for safety
  if (!text) return 'default';
  const lower = String(text).toLowerCase();
  return lower
    .replace(/&/g, 'and')
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .replace(/^-+|-+$/g, '')
    .slice(0, maxLength); // <-- Always truncate
}

function formatProsodyRate(rate) {
  logger.debug('Formatting prosody rate:', { inputRate: rate });
  
  // Handle numeric values (e.g., 1.2) as percentage
  if (typeof rate === 'number') {
    const percentage = `${(rate * 100).toFixed(0)}%`;
    logger.debug('Converted numeric rate to percentage:', { rate, percentage });
    return percentage;
  }
  
  // Handle string values
  const validRelativeRates = ['x-slow', 'slow', 'medium', 'fast', 'x-fast'];
  if (validRelativeRates.includes(rate)) {
    logger.debug('Using predefined rate:', { rate });
    return rate;
  }
  
  // Handle percentage strings
  if (typeof rate === 'string' && rate.endsWith('%')) {
    logger.debug('Using percentage string rate:', { rate });
    return rate;
  }
  
  // Default fallback
  logger.debug('Using default rate: medium');
  return 'medium';
}

// Add base URL configuration
const BASE_URL = process.env.BASE_URL || 'https://mentalfitness.store:3001';

function normalizeLocaleTag(language, separator = '-') {
  const fallback = 'en-US';
  const input = typeof language === 'string' && language.trim() !== '' ? language.trim() : fallback;
  const parts = input.replace(/-/g, '_').split('_').filter(Boolean);
  if (parts.length === 1) {
    const base = parts[0].toLowerCase();
    const region = base === 'en' ? 'US' : base.toUpperCase();
    return `${base}${separator}${region}`;
  }

  const locale = `${parts[0].toLowerCase()}${separator}${parts[1].toUpperCase()}`;
  return locale;
}

function resolveProductStorageSegments(options = {}) {
  const categorySegment = slugifyText(options.category || 'default');
  const productSource = options.productSlug || options.productName || options.productId || categorySegment;
  const productSegment = slugifyText(String(productSource || 'product'));

  return {
    localeSegment: normalizeLocaleTag(options.language, '_'),
    categorySegment,
    productSegment,
  };
}

function generateAudioPaths(text, options = {}) {
  // Always use the category from options, fallback to 'default'
  const {
    engine = 'azure',
    language = 'en-US',
    speaker = 'en-US-AriaNeural',
    category = 'default'
  } = options;

  // Log the category value used for path generation
  logger.debug('generateAudioPaths: category value', { category, optionsCategory: options.category || '<<undefined>>' });

  // Map speaker based on engine
  let folderSpeaker = speaker;
  if (engine === 'vits') {
    // For VITS, map speakers based on language and preference
    if (language === 'hi-IN' || language === 'hi') {
      // Hindi language - use local Hindi models
      if (speaker.includes('female') || speaker.includes('f') || speaker === 'hi-female') {
        folderSpeaker = 'hi-female';
      } else if (speaker.includes('male') || speaker.includes('m') || speaker === 'hi-male') {
        folderSpeaker = 'hi-male';
      } else {
        // Default to female for Hindi
        folderSpeaker = 'hi-female';
      }
    } else {
      // English language - use VCTK speakers
      if (speaker.includes('p225') || speaker.includes('p-225') || speaker.includes('female')) {
        folderSpeaker = 'p225';
      } else if (speaker.includes('p227') || speaker.includes('p-227') || speaker.includes('male')) {
        folderSpeaker = 'p227';
      } else if (speaker.includes('p230')) {
        folderSpeaker = 'p230';
      } else if (speaker.includes('p245')) {
        folderSpeaker = 'p245';
      } else {
        // Default VITS speaker for English
        folderSpeaker = 'p225';
      }
    }
    logger.debug('VITS speaker mapping in generateAudioPaths', { 
      originalSpeaker: speaker, 
      folderSpeaker, 
      language 
    });
  }
  // For Azure, keep the original speaker name

  const hash = hashMessage(text);
  const slug = slugifyText(text);
  const extension = 'aac'; // Use AAC for both Azure and VITS for Flutter compatibility

  // Build path: en-US/category/model/filename
  const categoryPath = category ? slugifyText(category) : 'default';
  const storageBase = options && options.storageType === 'products' ? 'products-audio' : 'audio-cache';
  const pathSegments = options && options.storageType === 'products'
    ? (() => {
        const productSegments = resolveProductStorageSegments(options);
        return [
          storageBase,
          productSegments.localeSegment,
          productSegments.categorySegment,
          productSegments.productSegment,
          folderSpeaker,
          `${slug}-${hash}.${extension}`
        ];
      })()
    : [
        storageBase,
        language,
        categoryPath,
        folderSpeaker,
        `${slug}-${hash}.${extension}`
      ];
  const relativePath = path.join(...pathSegments).replace(/\\/g, '/');

  return {
    relativePath,
    audioUrl: `${BASE_URL}/${relativePath}`
  };
}

// Helper to get supported styles for a voice
function getSupportedStyles(voiceShortName) {
  const voice = voicesList.find(v => v.ShortName === voiceShortName);
  return voice && Array.isArray(voice.StyleList) ? voice.StyleList : [];
}

/**
 * Convert custom markup notation to proper SSML inline tags.
 *
 * Supported markup:
 *   [pause:800]                  → <break time="800ms"/>
 *   [silence:500]                → <break time="500ms"/>
 *   [personality:Warm]…[/personality] → <mstts:express-as style="warm">…</mstts:express-as>
 *   [rate:slow]…[/rate]          → <prosody rate="slow">…</prosody>
 *   [pitch:high]…[/pitch]        → <prosody pitch="high">…</prosody>
 *   [volume:loud]…[/volume]      → <prosody volume="loud">…</prosody>
 *   **text**                     → <emphasis level="strong">text</emphasis>
 *   *text*                       → <emphasis level="moderate">text</emphasis>
 */
function convertMarkupToSSML(text) {
  if (!text || typeof text !== 'string') return text || '';

  let result = text;

  // 1. [pause:N]  – treat N as milliseconds
  result = result.replace(/\[pause:(\d+)\]/gi, (_, ms) => `<break time="${ms}ms"/>`);

  // 2. [silence:N] – treat N as milliseconds
  result = result.replace(/\[silence:(\d+)\]/gi, (_, ms) => `<break time="${ms}ms"/>`);

  // 3. [personality:X]…[/personality]
  result = result.replace(
    /\[personality:([^\]]+)\]([\s\S]*?)\[\/personality\]/gi,
    (_, style, content) => `<mstts:express-as style="${style.trim().toLowerCase()}">${content}</mstts:express-as>`
  );

  // 4. [rate:X]…[/rate]
  result = result.replace(
    /\[rate:([^\]]+)\]([\s\S]*?)\[\/rate\]/gi,
    (_, rate, content) => `<prosody rate="${rate.trim()}">${content}</prosody>`
  );

  // 5. [pitch:X]…[/pitch]
  result = result.replace(
    /\[pitch:([^\]]+)\]([\s\S]*?)\[\/pitch\]/gi,
    (_, pitch, content) => `<prosody pitch="${pitch.trim()}">${content}</prosody>`
  );

  // 6. [volume:X]…[/volume]
  result = result.replace(
    /\[volume:([^\]]+)\]([\s\S]*?)\[\/volume\]/gi,
    (_, vol, content) => `<prosody volume="${vol.trim()}">${content}</prosody>`
  );

  // 7. **strong emphasis** (must come before single *)
  result = result.replace(/\*\*([^*]+)\*\*/g, '<emphasis level="strong">$1</emphasis>');

  // 8. *moderate emphasis*
  result = result.replace(/\*([^*]+)\*/g, '<emphasis level="moderate">$1</emphasis>');

  // 9. Strip any remaining unrecognised [tag] or [/tag] so they are not read aloud
  result = result.replace(/\[[^\]]*\]/g, '');

  logger.debug('convertMarkupToSSML', {
    inputLength: text.length,
    outputLength: result.length,
    sample: result.substring(0, 200)
  });

  return result;
}

// Generate SSML with style only if supported.
// For Azure multilingual voices (Ava, Ada, etc.), the <lang xml:lang="..."> element
// is what drives accent switching. The root xml:lang on <speak> should stay "en-US"
// (the voice's native locale) while <lang> wraps the text with the target accent.
function buildSSML({ text, language, speaker, speakerStyle, speakerPersonality, ssml, prosodyRate, prosodyPitch, prosodyVolume }) {
  if (ssml) return ssml;

  // Convert custom markup to SSML inline tags before wrapping
  const processedText = convertMarkupToSSML(text);

  // Determine if we need a global prosody wrapper
  const needsProsody = (prosodyRate && prosodyRate !== 'medium') ||
                       (prosodyPitch && prosodyPitch !== 'medium') ||
                       (prosodyVolume && prosodyVolume !== 'medium');

  // Root xml:lang stays "en-US" (voice's native locale).
  // The <lang> element below is what tells the multilingual voice which accent to use.
  let ssmlContent = `<?xml version="1.0"?>
<speak version="1.0"
       xmlns="http://www.w3.org/2001/10/synthesis"
       xmlns:mstts="http://www.w3.org/2001/mstts"
       xmlns:emo="http://www.w3.org/2009/10/emotionml"
       xml:lang="en-US">
  <voice name="${speaker}">`;

  if (speakerPersonality) {
    ssmlContent += `\n    <mstts:express-as role="${speakerPersonality}">`;
  }
  if (speakerStyle) {
    ssmlContent += `\n    <mstts:express-as style="${speakerStyle}">`;
  }

  // Apply global prosody settings if any differ from medium
  if (needsProsody) {
    let prosodyAttrs = '';
    if (prosodyRate && prosodyRate !== 'medium') prosodyAttrs += ` rate="${prosodyRate}"`;
    if (prosodyPitch && prosodyPitch !== 'medium') prosodyAttrs += ` pitch="${prosodyPitch}"`;
    if (prosodyVolume && prosodyVolume !== 'medium') prosodyAttrs += ` volume="${prosodyVolume}"`;
    ssmlContent += `\n      <prosody${prosodyAttrs}>`;
  }

  // <lang xml:lang="..."> is what triggers accent switching for multilingual voices
  ssmlContent += `\n      <lang xml:lang="${language}">${processedText}</lang>`;

  if (needsProsody) {
    ssmlContent += `\n      </prosody>`;
  }

  if (speakerStyle) {
    ssmlContent += `\n    </mstts:express-as>`;
  }
  if (speakerPersonality) {
    ssmlContent += `\n    </mstts:express-as>`;
  }

  ssmlContent += `\n  </voice>\n</speak>`;
  console.log('[buildSSML] language:', language, 'speaker:', speaker, 'style:', speakerStyle, 'personality:', speakerPersonality);
  console.log('[buildSSML] SSML output:\n', ssmlContent);
  return ssmlContent;
}

async function generateAudioFile(text, filePath, options) {
  // Always load credentials from environment variables
  const AZURE_KEY = process.env.AZURE_KEY;
  const AZURE_REGION = process.env.AZURE_REGION;

  logger.debug('Azure credentials', { AZURE_KEY, AZURE_REGION });

  if (options.engine === 'azure') {
    // Use provided SSML or build it - don't override the speaker
    let usedSSML;
    if (options.ssml) {
      logger.info('Using SSML from document for TTS', { filePath, text, ssml: options.ssml });
      usedSSML = options.ssml;
    } else {
      usedSSML = buildSSML({
        text,
        language: options.language,
        speaker: options.speaker,
        speakerStyle: options.speakerStyle,
        speakerPersonality: options.speakerPersonality,
        prosodyRate: options.prosodyRate,
        prosodyPitch: options.prosodyPitch,
        prosodyVolume: options.prosodyVolume
      });
      logger.info('Using backend-generated SSML for TTS', { filePath, text, ssml: usedSSML });
    }

    console.log('[generateAudioFile] Final TTS params — language:', options.language, 'speaker:', options.speaker, 'style:', options.speakerStyle);
    console.log('[generateAudioFile] SSML sent to Azure:\n', usedSSML);

    try {
      // Ensure parent directory exists before writing file
      const dirPath = path.dirname(filePath);
      if (!fs.existsSync(dirPath)) {
        fs.mkdirSync(dirPath, { recursive: true });
      }

      const response = await axios.post(
        'https://centralindia.tts.speech.microsoft.com/cognitiveservices/v1',
        usedSSML,
        {
          headers: {
            'Ocp-Apim-Subscription-Key': AZURE_KEY,
            'Content-Type': 'application/ssml+xml',
            'X-Microsoft-OutputFormat': 'riff-48khz-16bit-mono-pcm', // Request WAV format for AAC conversion
            'User-Agent': 'MotivationAppClient',
          },
          responseType: 'arraybuffer',
        }
      );
      if (!response.data || response.data.length === 0) throw new Error('Empty response from Azure TTS');
      
      // Save WAV temporarily then convert to AAC
      const tempWavPath = filePath.replace('.aac', '.wav');
      fs.writeFileSync(tempWavPath, response.data);
      
      // Convert WAV to AAC using FFmpeg
      const ffmpegCommand = `ffmpeg -i "${tempWavPath}" -c:a aac -b:a 192k -ac 1 -ar 48000 "${filePath}" -y`;
      logger.debug('Azure WAV to AAC conversion command:', { ffmpegCommand });
      
      execSync(ffmpegCommand, { stdio: 'pipe' });
      logger.info('Generated Azure audio and converted to AAC', { 
        filePath, 
        fileSize: fs.existsSync(filePath) ? fs.statSync(filePath).size : 0,
        category: options.category 
      });
      
      // Clean up temporary WAV file
      if (fs.existsSync(tempWavPath)) {
        fs.unlinkSync(tempWavPath);
        logger.debug('Cleaned up temporary Azure WAV file', { tempWavPath });
      }
    } catch (err) {
      if (err.response && err.response.data) {
        logger.error('Azure TTS error details', { data: err.response.data });
        console.log('Azure TTS error details:', { data: err.response.data });
      }
      logger.error('Azure TTS error', {
        error: err.message,
        filePath,
        requestBody: usedSSML
      });
      throw err;
    }
  } else {
    try {
      const hash = hashMessage(text); // Add missing hash variable
      const tempTextPath = path.join(TEMP_TEXT_DIR, `${hash}.txt`);
      
      // Process SSML for VITS if provided, otherwise use text directly
      let processedText = text;
      let vitsParams = {
        speed: options.speed || 1.0,
        noise: options.noise || 0.667,
        noiseW: options.noiseW || 0.8
      };

      if (options.ssml && options.ssml !== text) {
        logger.info('Processing SSML for VITS', { ssml: options.ssml });
        const ssmlResult = parseSSMLForVITS(options.ssml);
        processedText = enhanceTextForVITS(ssmlResult.text, ssmlResult.vitsParams);
        
        // Apply SSML-derived parameters
        if (ssmlResult.vitsParams.length_scale) {
          vitsParams.speed = ssmlResult.vitsParams.length_scale;
        }
        if (ssmlResult.vitsParams.noise_scale) {
          vitsParams.noise = ssmlResult.vitsParams.noise_scale;
        }
        if (ssmlResult.vitsParams.noise_scale_w) {
          vitsParams.noiseW = ssmlResult.vitsParams.noise_scale_w;
        }
        
        logger.info('Applied SSML parameters to VITS', { 
          originalText: text,
          processedText,
          vitsParams,
          ssmlParams: ssmlResult.vitsParams
        });
      }
      
      fs.writeFileSync(tempTextPath, processedText);
      
      // Ensure the output directory exists
      const outputDir = path.dirname(filePath);
      if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
      }
      
      // For VITS, generate WAV first, then convert to AAC
      const tempWavPath = filePath.replace('.aac', '.wav');
      
      // Determine speaker mapping for VITS
      let vitsSpeaker = options.speaker || 'p225';
      if (options.language === 'hi-IN' || options.language === 'hi') {
        // Hindi language - use local Hindi models
        if (vitsSpeaker.includes('female') || vitsSpeaker.includes('f') || vitsSpeaker === 'hi-female') {
          vitsSpeaker = 'hi-female';
        } else if (vitsSpeaker.includes('male') || vitsSpeaker.includes('m') || vitsSpeaker === 'hi-male') {
          vitsSpeaker = 'hi-male';
        } else {
          vitsSpeaker = 'hi-female'; // Default to female for Hindi
        }
      } else {
        // English language - use VCTK speakers
        if (vitsSpeaker.includes('p225') || vitsSpeaker.includes('p-225') || vitsSpeaker.includes('female')) {
          vitsSpeaker = 'p225';
        } else if (vitsSpeaker.includes('p227') || vitsSpeaker.includes('p-227') || vitsSpeaker.includes('male')) {
          vitsSpeaker = 'p227';
        } else if (vitsSpeaker.includes('p230')) {
          vitsSpeaker = 'p230';
        } else if (vitsSpeaker.includes('p245')) {
          vitsSpeaker = 'p245';
        } else {
          vitsSpeaker = 'p225'; // Default VITS speaker for English
        }
      }
      
      // Provide default values for VITS parameters if not specified
      const speed = vitsParams.speed;
      const noise = vitsParams.noise;
      const noiseW = vitsParams.noiseW;
      
      // Use the virtual environment's Python executable directly
      const pythonPath = path.join(process.cwd(), 'tts-venv', 'bin', 'python3');
      const command = `${pythonPath} run_vits_inference.py --text_file "${tempTextPath}" --output "${tempWavPath}" --speaker "${vitsSpeaker}" --language "${options.language || 'en'}" --length_scale ${speed} --noise_scale ${noise} --noise_scale_w ${noiseW}`;
      
      // Set environment variables for TTS library
      const env = {
        ...process.env,
        MPLCONFIGDIR: path.join(process.cwd(), 'tmp', 'matplotlib'),
        XDG_CACHE_HOME: path.join(process.cwd(), 'tmp', 'cache'),
        HOME: process.cwd(),
        COQUI_TTS_CACHE_DIR: '/var/www/clients/client1/web63/web/tts-backend/home/mywebmotivation/.local/share/tts'
      };
      
      logger.debug('VITS command details:', { 
        command, 
        outputPath: tempWavPath,
        finalPath: filePath,
        relativePath: path.relative(process.cwd(), filePath),
        pythonPath,
        speaker: vitsSpeaker,
        language: options.language || 'en',
        processedText: processedText.substring(0, 100) + '...',
        vitsParams,
        env: { COQUI_TTS_CACHE_DIR: env.COQUI_TTS_CACHE_DIR } 
      });
      
      // Generate WAV file with VITS
      execSync(command, { stdio: 'pipe', env });
      logger.info('Generated VITS WAV audio successfully', { 
        tempWavPath, 
        fileSize: fs.existsSync(tempWavPath) ? fs.statSync(tempWavPath).size : 0 
      });
      
      // Convert WAV to AAC using FFmpeg
      const ffmpegCommand = `ffmpeg -i "${tempWavPath}" -c:a aac -b:a 192k -ac 1 -ar 48000 "${filePath}" -y`;
      logger.debug('VITS WAV to AAC conversion command:', { ffmpegCommand });
      
      execSync(ffmpegCommand, { stdio: 'pipe' });
      logger.info('Converted VITS audio to AAC', { 
        finalPath: filePath,
        relativePath: path.relative(process.cwd(), filePath),
        fileSize: fs.existsSync(filePath) ? fs.statSync(filePath).size : 0 
      });
      
      // Clean up temporary files
      fs.unlinkSync(tempTextPath);
      if (fs.existsSync(tempWavPath)) {
        fs.unlinkSync(tempWavPath);
        logger.debug('Cleaned up temporary WAV file', { tempWavPath });
      }
      
    } catch (error) {
      logger.error('VITS generation error', { error: error.message, stack: error.stack });
      throw error;
    }
  }
}

// Optional: Add a cleanup function for temp files
function cleanupTempFiles() {
  try {
    const files = fs.readdirSync(TEMP_TEXT_DIR);
    for (const file of files) {
      fs.unlinkSync(path.join(TEMP_TEXT_DIR, file));
    }
    console.log('✅ Cleaned up temporary text files');
  } catch (error) {
    console.error('❌ Error cleaning temp files:', error.message);
  }
}

function someFunctionThatUsesText(text, options) {
  if (typeof text !== 'string') {
      console.error('audioGenerator.js: text is not a string', { text });
      throw new Error('audioGenerator.js: text is not a string');
  }
  // Now it's safe to call .toLowerCase()
  const lower = text.toLowerCase();
  // ...existing code...
}

// Remove 'export' from this function declaration
async function generateAudioForMessage(text, options = {}) {
  let relativePath, audioUrl, filePath;
  
  // Always use the category from options, fallback to 'default'
  const {
    engine = 'azure',
    language = 'en-US',
    speaker = 'en-US-AriaNeural',
    category = 'default'
  } = options;

  // Log the category value used for path generation
  logger.debug('generateAudioPaths: category value', { category, optionsCategory: options.category || '<<undefined>>' });

  // Map speaker based on engine
  let folderSpeaker = speaker;
  if (engine === 'vits') {
    // For VITS, map speakers based on language and preference
    if (language === 'hi-IN' || language === 'hi') {
      // Hindi language - use local Hindi models
      if (speaker.includes('female') || speaker.includes('f') || speaker === 'hi-female') {
        folderSpeaker = 'hi-female';
      } else if (speaker.includes('male') || speaker.includes('m') || speaker === 'hi-male') {
        folderSpeaker = 'hi-male';
      } else {
        // Default to female for Hindi
        folderSpeaker = 'hi-female';
      }
    } else {
      // English language - use VCTK speakers
      if (speaker.includes('p225') || speaker.includes('p-225') || speaker.includes('female')) {
        folderSpeaker = 'p225';
      } else if (speaker.includes('p227') || speaker.includes('p-227') || speaker.includes('male')) {
        folderSpeaker = 'p227';
      } else if (speaker.includes('p230')) {
        folderSpeaker = 'p230';
      } else if (speaker.includes('p245')) {
        folderSpeaker = 'p245';
      } else {
        // Default VITS speaker for English
        folderSpeaker = 'p225';
      }
    }
    logger.debug('VITS speaker mapping', { 
      originalSpeaker: speaker, 
      folderSpeaker, 
      language 
    });
  }
  // For Azure, keep the original speaker name

  const hash = hashMessage(text);
  const slug = slugifyText(text);
  const extension = 'aac'; // Use AAC for both Azure and VITS for Flutter compatibility

  // Build path: en-US/category/model/filename
  const categoryPath = category ? slugifyText(category) : 'default';
  const storageBase2 = options && options.storageType === 'products' ? 'products-audio' : 'audio-cache';
  const storageBaseDir = options && options.storageType === 'products' ? PRODUCTS_AUDIO_BASE : AUDIO_CACHE_BASE;
  if (options && options.storageType === 'products') {
    const productSegments = resolveProductStorageSegments(options);
    relativePath = path.join(
      storageBase2,
      productSegments.localeSegment,
      productSegments.categorySegment,
      productSegments.productSegment,
      folderSpeaker,
      `${slug}-${hash}.${extension}`
    ).replace(/\\/g, '/');

    audioUrl = `${BASE_URL}/${relativePath}`;
    filePath = path.join(
      storageBaseDir,
      productSegments.localeSegment,
      productSegments.categorySegment,
      productSegments.productSegment,
      folderSpeaker,
      `${slug}-${hash}.${extension}`
    );
  } else {
    relativePath = path.join(
      storageBase2,
      language,
      categoryPath,
      folderSpeaker,
      `${slug}-${hash}.${extension}`
    ).replace(/\\/g, '/');

    audioUrl = `${BASE_URL}/${relativePath}`;
    filePath = path.join(storageBaseDir, language, categoryPath, folderSpeaker, `${slug}-${hash}.${extension}`);
  }

  // Check if audio file already exists
  if (fs.existsSync(filePath)) {
    logger.info('Audio file already exists, skipping generation', { filePath, engine, speaker: folderSpeaker });
    return { relativePath, audioUrl, filePath };
  }

  // Proceed with audio generation
  await generateAudioFile(text, filePath, options);

  return { relativePath, audioUrl, filePath };
}

// Update supported styles for AvaMultilingualNeural
const supportedStyles = {
  'en-US-AvaMultilingualNeural': [
    'cheerful', 'sad', 'angry', 'excited', 'friendly', 'hopeful', 'shouting',
    'terrified', 'unfriendly', 'whispering', 'newscast', 'customerservice',
    'narration-professional', 'narration-casual'
  ],
  // ...add more voices as needed...
};

function getValidSpeakerStyle(speaker, style) {
  if (!style) return undefined;
  if (supportedStyles[speaker] && supportedStyles[speaker].includes(style)) {
    return style;
  }
  // fallback to a default supported style
  return supportedStyles[speaker] ? supportedStyles[speaker][0] : undefined;
}

// Fix exports - ensure all needed functions are exported
export {
  generateAudioPaths,
  generateAudioForMessage,
  cleanupTempFiles
};
