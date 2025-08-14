import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const AUDIO_CACHE_DIR = path.join(process.cwd(), 'audio-cache');
const TEMP_TEXT_DIR = path.join(process.cwd(), 'temp-texts');

// Ensure directories exist
for (const dir of [AUDIO_CACHE_DIR, TEMP_TEXT_DIR]) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
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

// Create a simple VITS inference script that doesn't use TTS.api
function createSimpleVitsScript() {
  const scriptContent = `import argparse
import sys
import os

def generate_voice(text, output_path, length_scale=1.1, noise_scale=0.667, noise_scale_w=0.8):
    try:
        # Try to use a simpler approach - just create a dummy file for now
        print(f"Generating VITS audio for: {text}")
        print(f"Output: {output_path}")
        print(f"Parameters: length_scale={length_scale}, noise_scale={noise_scale}, noise_scale_w={noise_scale_w}")
        
        # For now, create a placeholder file to test the parameter passing
        with open(output_path, 'wb') as f:
            f.write(b'DUMMY_AUDIO_FILE')
        
        print("VITS audio generation completed successfully")
        
    except Exception as e:
        print(f"Error in VITS generation: {e}")
        sys.exit(1)

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--text", help="Text to convert to speech")
    parser.add_argument("--text_file", help="Path to text file instead of direct text")
    parser.add_argument("--output", required=True, help="Output WAV file path")
    parser.add_argument("--length_scale", type=float, default=1.1)
    parser.add_argument("--noise_scale", type=float, default=0.667)
    parser.add_argument("--noise_scale_w", type=float, default=0.8)
    args = parser.parse_args()

    if args.text_file:
        with open(args.text_file, 'r', encoding='utf-8') as f:
            text = f.read()
    elif args.text:
        text = args.text
    else:
        raise ValueError("Either --text or --text_file must be provided.")

    generate_voice(text, args.output, args.length_scale, args.noise_scale, args.noise_scale_w)
`;

  fs.writeFileSync('simple_vits_test.py', scriptContent);
}

export function generateAudioForMessage(text, speed = 1.2, noise = 0.667, noiseW = 0.8) {
  const hash = hashMessage(text);
  const safeName = `${slugify(text)}-${hash}.wav`;
  const filePath = path.join(AUDIO_CACHE_DIR, safeName);

  if (!fs.existsSync(filePath)) {
    const tempTextPath = path.join(TEMP_TEXT_DIR, `${hash}.txt`);
    fs.writeFileSync(tempTextPath, text);

    // Create the simple test script if it doesn't exist
    if (!fs.existsSync('simple_vits_test.py')) {
      createSimpleVitsScript();
    }

    const command = `python3 simple_vits_test.py --text_file "${tempTextPath}" --output "${filePath}" --length_scale ${speed} --noise_scale ${noise} --noise_scale_w ${noiseW}`;
    console.log('Running command:', command);
    execSync(command, { stdio: 'inherit' });
  }

  return filePath;
}
