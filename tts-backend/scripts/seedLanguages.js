import mongoose from 'mongoose';
import Language from '../models/Language.js';

// List of Azure TTS supported languages (India + major world languages)
const languages = [
  // Indian Languages
  { code: 'hi-IN', name: 'Hindi (India)', localName: 'हिन्दी' },
  { code: 'bn-IN', name: 'Bengali (India)', localName: 'বাংলা' },
  { code: 'gu-IN', name: 'Gujarati (India)', localName: 'ગુજરાતી' },
  { code: 'kn-IN', name: 'Kannada (India)', localName: 'ಕನ್ನಡ' },
  { code: 'ml-IN', name: 'Malayalam (India)', localName: 'മലയാളം' },
  { code: 'mr-IN', name: 'Marathi (India)', localName: 'मराठी' },
  { code: 'or-IN', name: 'Odia (India)', localName: 'ଓଡ଼ିଆ' },
  { code: 'pa-IN', name: 'Punjabi (India)', localName: 'ਪੰਜਾਬੀ' },
  { code: 'ta-IN', name: 'Tamil (India)', localName: 'தமிழ்' },
  { code: 'te-IN', name: 'Telugu (India)', localName: 'తెలుగు' },
  { code: 'ur-IN', name: 'Urdu (India)', localName: 'اُردُو' },
  // Major World Languages
  { code: 'en-US', name: 'English (United States)', localName: 'US-English' },
  { code: 'en-GB', name: 'English (United Kingdom)', localName: 'UK-English' },
  { code: 'en-AU', name: 'English (Australia)', localName: 'AUS-English' },
  { code: 'en-CA', name: 'English (Canada)', localName: 'CA-English' },
  { code: 'en-IN', name: 'English (Canada)', localName: 'IND-English' },
  { code: 'en-NZ', name: 'English (New Zealand)', localName: 'NZ-English' },
  { code: 'ar-SA', name: 'Arabic (Saudi Arabia)', localName: 'العربية' },
  { code: 'ar-EG', name: 'Arabic (Egypt)', localName: 'العربية' },
  { code: 'fr-FR', name: 'French (France)', localName: 'FR-Français' },
  { code: 'fr-CA', name: 'French (Canada)', localName: 'CA-Français' },
  { code: 'de-DE', name: 'German (Germany)', localName: 'Deutsch' },
  { code: 'es-ES', name: 'Spanish (Spain)', localName: 'SP-Español' },
  { code: 'es-MX', name: 'Spanish (Mexico)', localName: 'MEX-Español' },
  { code: 'it-IT', name: 'Italian (Italy)', localName: 'Italiano' },
  { code: 'ja-JP', name: 'Japanese (Japan)', localName: '日本語' },
  { code: 'ko-KR', name: 'Korean (Korea)', localName: '한국어' },
  { code: 'ru-RU', name: 'Russian (Russia)', localName: 'Русский' },
  { code: 'zh-CN', name: 'Chinese (Mandarin, Simplified)', localName: '中文(普通话)' },
  { code: 'zh-TW', name: 'Chinese (Taiwanese Mandarin)', localName: '中文(台灣)' },
  { code: 'zh-HK', name: 'Chinese (Cantonese, Hong Kong)', localName: '粵語' },
  { code: 'tr-TR', name: 'Turkish (Turkey)', localName: 'Türkçe' },
  { code: 'vi-VN', name: 'Vietnamese (Vietnam)', localName: 'Tiếng Việt' },
  { code: 'th-TH', name: 'Thai (Thailand)', localName: 'ไทย' },
  { code: 'id-ID', name: 'Indonesian (Indonesia)', localName: 'Bahasa Indonesia' },
  { code: 'fa-IR', name: 'Persian (Iran)', localName: 'فارسی' },
  { code: 'pt-BR', name: 'Portuguese (Brazil)', localName: 'BR-Português' },
  { code: 'pt-PT', name: 'Portuguese (Portugal)', localName: 'PT-Português' },
  { code: 'pl-PL', name: 'Polish (Poland)', localName: 'Polski' },
  { code: 'uk-UA', name: 'Ukrainian (Ukraine)', localName: 'Українська' },
  { code: 'nl-NL', name: 'Dutch (Netherlands)', localName: 'Nederlands' },
  { code: 'sv-SE', name: 'Swedish (Sweden)', localName: 'Svenska' },
  { code: 'fi-FI', name: 'Finnish (Finland)', localName: 'Suomi' },
  { code: 'no-NO', name: 'Norwegian (Norway)', localName: 'Norsk' },
  { code: 'da-DK', name: 'Danish (Denmark)', localName: 'Dansk' },
  { code: 'he-IL', name: 'Hebrew (Israel)', localName: 'עברית' },
  { code: 'el-GR', name: 'Greek (Greece)', localName: 'Ελληνικά' },
  { code: 'cs-CZ', name: 'Czech (Czech Republic)', localName: 'Čeština' },
  { code: 'hu-HU', name: 'Hungarian (Hungary)', localName: 'Magyar' },
  { code: 'ro-RO', name: 'Romanian (Romania)', localName: 'Română' },
  { code: 'sk-SK', name: 'Slovak (Slovakia)', localName: 'Slovenčina' },
  { code: 'bg-BG', name: 'Bulgarian (Bulgaria)', localName: 'Български' },
  { code: 'hr-HR', name: 'Croatian (Croatia)', localName: 'Hrvatski' },
  { code: 'sl-SI', name: 'Slovenian (Slovenia)', localName: 'Slovenščina' },
  // Add more as needed from Azure docs
];

async function seed() {
  await mongoose.connect('mongodb://pawan:pragati123..@127.0.0.1:27017/motivation');
  await Language.deleteMany({});
  await Language.insertMany(languages);
  console.log('✅ Seeded languages');
  await mongoose.disconnect();
}

seed().catch(err => {
  console.error('❌ Error seeding languages:', err);
  process.exit(1);
});
