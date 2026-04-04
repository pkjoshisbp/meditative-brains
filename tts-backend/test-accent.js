#!/usr/bin/env node
/**
 * Test script: Generate audio with en-US vs en-IN accent using Azure TTS.
 * Produces two audio files so you can listen and compare accents.
 *
 * Usage:  node test-accent.js
 *         node test-accent.js "Your custom test sentence here"
 */
import dotenv from 'dotenv';
dotenv.config();

import axios from 'axios';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const AZURE_KEY = process.env.AZURE_KEY;
const AZURE_REGION = process.env.AZURE_REGION || 'centralindia';
const TTS_ENDPOINT = `https://${AZURE_REGION}.tts.speech.microsoft.com/cognitiveservices/v1`;

const TEXT = process.argv[2] || 'Hello, welcome to our meditation program. Today we will practice mindfulness and inner peace together.';
const SPEAKER = process.argv[3] || 'en-US-AvaMultilingualNeural';

const OUTPUT_DIR = path.join(process.cwd(), 'test-accent-output');
if (!fs.existsSync(OUTPUT_DIR)) fs.mkdirSync(OUTPUT_DIR, { recursive: true });

// ── SSML builders ────────────────────────────────────────────────

/** Audiobook-style SSML — root en-US, <lang> wrapper drives accent */
function buildAudiobookSSML(text, language, speaker) {
  return `<?xml version="1.0"?>
<speak version="1.0"
       xmlns="http://www.w3.org/2001/10/synthesis"
       xmlns:mstts="http://www.w3.org/2001/mstts"
       xml:lang="en-US">
  <voice name="${speaker}">
    <lang xml:lang="${language}">${text}</lang>
  </voice>
</speak>`;
}

/** Attention-guide-style SSML — root en-US, <lang> + express-as + prosody */
function buildAttentionGuideSSML(text, language, speaker) {
  return `<?xml version="1.0"?>
<speak version="1.0"
       xmlns="http://www.w3.org/2001/10/synthesis"
       xmlns:mstts="http://www.w3.org/2001/mstts"
       xml:lang="en-US">
  <voice name="${speaker}">
    <mstts:express-as role="assistant">
      <lang xml:lang="${language}">
        <prosody rate="medium">${text}</prosody>
      </lang>
    </mstts:express-as>
  </voice>
</speak>`;
}

// ── Azure request ────────────────────────────────────────────────

async function generateAudio(ssml, outputPath) {
  const response = await axios.post(TTS_ENDPOINT, ssml, {
    headers: {
      'Ocp-Apim-Subscription-Key': AZURE_KEY,
      'Content-Type': 'application/ssml+xml',
      'X-Microsoft-OutputFormat': 'audio-48khz-192kbitrate-mono-mp3',
      'User-Agent': 'AccentTestScript',
    },
    responseType: 'arraybuffer',
  });
  fs.writeFileSync(outputPath, response.data);
  const sizeKB = (fs.statSync(outputPath).size / 1024).toFixed(1);
  return sizeKB;
}

// ── Main ─────────────────────────────────────────────────────────

async function main() {
  console.log('='.repeat(70));
  console.log('ACCENT TEST — en-US vs en-IN');
  console.log('='.repeat(70));
  console.log(`Speaker : ${SPEAKER}`);
  console.log(`Text    : ${TEXT}`);
  console.log(`Output  : ${OUTPUT_DIR}/`);
  console.log('='.repeat(70));

  const tests = [
    // Audiobook-style SSML (buildSSML path)
    { label: '1. en-US  (audiobook SSML)', lang: 'en-US', builder: buildAudiobookSSML,        file: '1_enUS_audiobook.mp3' },
    { label: '2. en-IN  (audiobook SSML)', lang: 'en-IN', builder: buildAudiobookSSML,        file: '2_enIN_audiobook.mp3' },
    // Attention-guide-style SSML (TTS messages path)
    { label: '3. en-US  (tts-msg  SSML)', lang: 'en-US', builder: buildAttentionGuideSSML,   file: '3_enUS_ttsmsg.mp3' },
    { label: '4. en-IN  (tts-msg  SSML)', lang: 'en-IN', builder: buildAttentionGuideSSML,   file: '4_enIN_ttsmsg.mp3' },
  ];

  for (const t of tests) {
    const ssml = t.builder(TEXT, t.lang, SPEAKER);
    const outPath = path.join(OUTPUT_DIR, t.file);

    console.log(`\n── ${t.label} ──`);
    console.log('SSML:\n' + ssml);

    try {
      const sizeKB = await generateAudio(ssml, outPath);
      console.log(`✅  Saved ${t.file}  (${sizeKB} KB)`);
    } catch (err) {
      const detail = err.response?.data
        ? Buffer.from(err.response.data).toString('utf-8').substring(0, 300)
        : err.message;
      console.error(`❌  FAILED: ${detail}`);
    }
  }

  console.log('\n' + '='.repeat(70));
  console.log('DONE — Compare the .mp3 files:');
  console.log('  File 1 vs 2: same SSML style, different accent (en-US vs en-IN)');
  console.log('  File 2 vs 4: both en-IN, audiobook style vs tts-msg style (should sound similar)');
  console.log('='.repeat(70));
}

main().catch(err => { console.error('Fatal:', err.message); process.exit(1); });
