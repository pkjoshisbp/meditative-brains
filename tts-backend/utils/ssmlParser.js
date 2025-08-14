// SSML Processing for VITS
// This module extracts useful information from SSML tags and applies them to VITS parameters

import logger from './logger.js';

/**
 * Parse SSML and extract text with VITS-compatible parameters
 * @param {string} ssml - SSML content
 * @returns {Object} - {text, vitsParams}
 */
export function parseSSMLForVITS(ssml) {
  if (!ssml || typeof ssml !== 'string') {
    return { text: ssml || '', vitsParams: {} };
  }

  let cleanText = ssml;
  const vitsParams = {
    length_scale: 1.0,    // Speed control (default)
    noise_scale: 0.667,   // Speech variation
    noise_scale_w: 0.8,   // Intonation variation
    pauses: []            // Manual pause insertions
  };

  logger.debug('Processing SSML for VITS', { originalSSML: ssml });

  try {
    // 1. Handle <break> tags - convert to pauses in text
    cleanText = cleanText.replace(/<break\s+time="([^"]+)"\s*\/?>/gi, (match, time) => {
      const seconds = parseTimeToSeconds(time);
      if (seconds > 0) {
        // Add appropriate pause representation
        if (seconds <= 0.3) return '... ';
        else if (seconds <= 0.7) return '...... ';
        else if (seconds <= 1.0) return '......... ';
        else return '.............. ';
      }
      return ' ';
    });

    // Handle <break> without time attribute (default pause)
    cleanText = cleanText.replace(/<break\s*\/?>/gi, '... ');

    // 2. Handle <prosody rate=""> tags - adjust speech speed
    cleanText = cleanText.replace(/<prosody\s+rate="([^"]+)"[^>]*>(.*?)<\/prosody>/gi, (match, rate, content) => {
      const speedMultiplier = parseRateToMultiplier(rate);
      
      // Adjust global length_scale based on prosody
      if (speedMultiplier !== 1.0) {
        vitsParams.length_scale = vitsParams.length_scale / speedMultiplier;
        logger.debug('Adjusted VITS speed from prosody', { rate, speedMultiplier, newLengthScale: vitsParams.length_scale });
      }
      
      return content; // Return just the content
    });

    // 3. Handle <emphasis> tags - adjust intonation
    cleanText = cleanText.replace(/<emphasis\s+level="([^"]+)"[^>]*>(.*?)<\/emphasis>/gi, (match, level, content) => {
      // Adjust noise_scale_w for emphasis
      switch (level.toLowerCase()) {
        case 'strong':
          vitsParams.noise_scale_w = Math.min(1.0, vitsParams.noise_scale_w + 0.2);
          return `**${content}**`; // Add visual emphasis markers
        case 'moderate':
          vitsParams.noise_scale_w = Math.min(1.0, vitsParams.noise_scale_w + 0.1);
          return `*${content}*`;
        case 'reduced':
          vitsParams.noise_scale_w = Math.max(0.2, vitsParams.noise_scale_w - 0.1);
          return content;
        default:
          return content;
      }
    });

    // Handle <emphasis> without level (default to moderate)
    cleanText = cleanText.replace(/<emphasis[^>]*>(.*?)<\/emphasis>/gi, (match, content) => {
      vitsParams.noise_scale_w = Math.min(1.0, vitsParams.noise_scale_w + 0.1);
      return `*${content}*`;
    });

    // 4. Handle <say-as> tags - mostly just extract content for VITS
    cleanText = cleanText.replace(/<say-as\s+interpret-as="([^"]+)"[^>]*>(.*?)<\/say-as>/gi, (match, interpretAs, content) => {
      switch (interpretAs.toLowerCase()) {
        case 'spell-out':
        case 'characters':
          // Add spaces between characters for spelling
          return content.split('').join(' ');
        case 'number':
        case 'cardinal':
        case 'ordinal':
          // Keep numbers as-is for VITS
          return content;
        case 'date':
        case 'time':
          // VITS handles these reasonably well
          return content;
        default:
          return content;
      }
    });

    // 5. Handle <voice> tags - extract speaker info (if different from main)
    cleanText = cleanText.replace(/<voice\s+name="([^"]+)"[^>]*>(.*?)<\/voice>/gi, (match, voiceName, content) => {
      // Note: VITS can't change voice mid-sentence, but we can log this
      logger.debug('Voice change detected in SSML (VITS limitation)', { voiceName, content });
      return content;
    });

    // 6. Handle <phoneme> tags - VITS may not use these, but extract content
    cleanText = cleanText.replace(/<phoneme\s+ph="([^"]+)"[^>]*>(.*?)<\/phoneme>/gi, (match, phoneme, content) => {
      logger.debug('Phoneme notation detected (limited VITS support)', { phoneme, content });
      return content; // VITS will use its own phoneme processing
    });

    // 7. Handle <sub> tags (substitution)
    cleanText = cleanText.replace(/<sub\s+alias="([^"]+)"[^>]*>(.*?)<\/sub>/gi, (match, alias, content) => {
      return alias; // Use the alias pronunciation
    });

    // 8. Clean up remaining XML tags
    cleanText = cleanText.replace(/<[^>]+>/g, '');

    // 9. Clean up extra whitespace and normalize
    cleanText = cleanText
      .replace(/\s+/g, ' ')
      .replace(/\s*\.\.\.\s*/g, '... ')
      .trim();

    // 10. Normalize parameters to safe ranges
    vitsParams.length_scale = Math.max(0.5, Math.min(2.0, vitsParams.length_scale));
    vitsParams.noise_scale = Math.max(0.1, Math.min(1.0, vitsParams.noise_scale));
    vitsParams.noise_scale_w = Math.max(0.1, Math.min(1.0, vitsParams.noise_scale_w));

    logger.debug('SSML processed for VITS', { 
      cleanText, 
      vitsParams,
      originalLength: ssml.length,
      cleanLength: cleanText.length
    });

    return { text: cleanText, vitsParams };

  } catch (error) {
    logger.error('Error processing SSML for VITS', { error: error.message, ssml });
    // Fallback: strip all tags and return basic text
    const fallbackText = ssml.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
    return { text: fallbackText, vitsParams: {} };
  }
}

/**
 * Parse time strings like "2s", "500ms", "0.5s" to seconds
 */
function parseTimeToSeconds(timeStr) {
  if (!timeStr) return 0;
  
  const timeMatch = timeStr.match(/^(\d+(?:\.\d+)?)(ms|s)?$/i);
  if (!timeMatch) return 0;
  
  const value = parseFloat(timeMatch[1]);
  const unit = (timeMatch[2] || 's').toLowerCase();
  
  if (unit === 'ms') {
    return value / 1000;
  } else {
    return value;
  }
}

/**
 * Parse rate strings like "slow", "fast", "120%", "1.5" to speed multiplier
 */
function parseRateToMultiplier(rateStr) {
  if (!rateStr) return 1.0;
  
  const rate = rateStr.toLowerCase().trim();
  
  // Named rates
  const namedRates = {
    'x-slow': 0.5,
    'slow': 0.75,
    'medium': 1.0,
    'fast': 1.25,
    'x-fast': 1.5
  };
  
  if (namedRates[rate]) {
    return namedRates[rate];
  }
  
  // Percentage rates
  if (rate.endsWith('%')) {
    const percent = parseFloat(rate.slice(0, -1));
    if (!isNaN(percent)) {
      return percent / 100;
    }
  }
  
  // Numeric rates
  const numeric = parseFloat(rate);
  if (!isNaN(numeric) && numeric > 0) {
    return numeric;
  }
  
  return 1.0; // Default
}

/**
 * Generate enhanced text for VITS with punctuation cues
 */
export function enhanceTextForVITS(text, vitsParams = {}) {
  let enhanced = text;
  
  // Add slight pauses for better rhythm
  enhanced = enhanced.replace(/[,;]/g, '$&.'); // Add micro-pause after commas
  enhanced = enhanced.replace(/[.!?]+/g, '$&..'); // Add pause after sentences
  
  // Handle emphasis markers
  enhanced = enhanced.replace(/\*\*(.*?)\*\*/g, '$1'); // Remove strong emphasis markers
  enhanced = enhanced.replace(/\*(.*?)\*/g, '$1'); // Remove emphasis markers
  
  return enhanced;
}
