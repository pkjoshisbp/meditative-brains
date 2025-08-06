import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import axios from 'axios';
import logger from './logger.js';
import slugify from 'slugify'; // If not installed, run: npm install slugify

// Load voices list once at startup
import voicesList from '../azure-voices.json' assert { type: 'json' };

const AUDIO_CACHE_BASE = path.join(process.cwd(), 'audio-cache');
const TEMP_TEXT_DIR = path.join(process.cwd(), 'temp-texts');

// Create necessary directories
for (const dir of [AUDIO_CACHE_BASE, TEMP_TEXT_DIR]) {
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
const BASE_URL = process.env.BASE_URL || 'https://motivation.mywebsolutions.co.in:3000';

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

  const hash = hashMessage(text);
  const slug = slugifyText(text);
  const extension = engine === 'azure' ? 'mp3' : 'wav';

  // Build path: en-US/category/model/filename
  const categoryPath = category ? slugifyText(category) : 'default';
  const relativePath = path.join(
    'audio-cache',
    language,
    categoryPath,
    speaker,
    `${slug}-${hash}.${extension}`
  ).replace(/\\/g, '/');

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

// Generate SSML with style only if supported
function buildSSML({ text, language, speaker, speakerStyle, speakerPersonality, ssml }) {
  if (ssml) return ssml;

  // Use en-US as the base, wrap text in <lang xml:lang="en-IN">...</lang>
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

  ssmlContent += `\n      <lang xml:lang="${language}">${text}</lang>`;

  if (speakerStyle) {
    ssmlContent += `\n    </mstts:express-as>`;
  }
  if (speakerPersonality) {
    ssmlContent += `\n    </mstts:express-as>`;
  }

  ssmlContent += `\n  </voice>\n</speak>`;
  logger.debug('Generated SSML:', { ssml: ssmlContent, language });
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
        speakerPersonality: options.speakerPersonality
      });
      logger.info('Using backend-generated SSML for TTS', { filePath, text, ssml: usedSSML });
    }

    logger.debug('Final TTS request parameters:', { 
      language: options.language,
      speaker: options.speaker,
      style: options.speakerStyle,
      ssml: usedSSML
    });

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
            'X-Microsoft-OutputFormat': 'audio-24khz-160kbitrate-mono-mp3',
            'User-Agent': 'MotivationAppClient',
          },
          responseType: 'arraybuffer',
        }
      );
      if (!response.data || response.data.length === 0) throw new Error('Empty response from Azure TTS');
      fs.writeFileSync(filePath, response.data);
      logger.info('Generated Azure audio', { filePath, fileSize: response.data.length, category: options.category });
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
      fs.writeFileSync(tempTextPath, text);
      const command = `python3 run_vits_inference.py --text_file "${tempTextPath}" --output "${filePath}" --length_scale ${options.speed} --noise_scale ${options.noise} --noise_scale_w ${options.noiseW}`;
      execSync(command);
      logger.info('Generated VITS audio', { filePath });
      fs.unlinkSync(tempTextPath); // Clean up temp file
    } catch (error) {
      logger.error('VITS generation error', { error: error.message });
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

  const hash = hashMessage(text);
  const slug = slugifyText(text);
  const extension = engine === 'azure' ? 'mp3' : 'wav';

  // Build path: en-US/category/model/filename
  const categoryPath = category ? slugifyText(category) : 'default';
  relativePath = path.join(
    'audio-cache',
    language,
    categoryPath,
    speaker,
    `${slug}-${hash}.${extension}`
  ).replace(/\\/g, '/');

  audioUrl = `${BASE_URL}/${relativePath}`;
  filePath = path.join(AUDIO_CACHE_BASE, language, categoryPath, speaker, `${slug}-${hash}.${extension}`);

  // Check if audio file already exists
  if (fs.existsSync(filePath)) {
    logger.info('Audio file already exists, skipping generation', { filePath });
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
