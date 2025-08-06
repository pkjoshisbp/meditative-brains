import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const AUDIO_CACHE_DIR = path.join(process.cwd(), 'audio-cache');
const TEMP_TEXT_DIR = path.join(process.cwd(), 'temp-texts');

// Ensure directories exist
for (const dir of [AUDIO_CACHE_DIR, TEMP_TEXT_DIR]) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir);
}

function hashMessage(text) {
  return crypto.createHash('md5').update(text).digest('hex');
}

function slugify(text, maxLength = 30) {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .substring(0, maxLength);
}

export function generateAudioForMessage(text, speed = 1.2, noise = 0.667, noiseW = 0.8) {
  const hash = hashMessage(text);
  const safeName = `${slugify(text)}-${hash}.wav`;
  const filePath = path.join(AUDIO_CACHE_DIR, safeName);

  if (!fs.existsSync(filePath)) {
    const tempTextPath = path.join(TEMP_TEXT_DIR, `${hash}.txt`);
    fs.writeFileSync(tempTextPath, text);

    const command = `python3 run_vits_inference.py --text_file "${tempTextPath}" --output "${filePath}" --length_scale ${speed} --noise_scale ${noise} --noise_scale_w ${noiseW}`;
    execSync(command);
  }

  return filePath;
}
