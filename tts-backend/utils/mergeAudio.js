import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const OUTPUT_DIR = path.join(process.cwd(), 'final-audio');

if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR);
}

export function mergeVoiceWithMusic(voicePath, musicPath, repeatCount = 3, intervalMs = 10000) {
    const baseName = path.basename(voicePath, '.wav');
    const outputPath = path.join(OUTPUT_DIR, `${baseName}_merged.wav`);

    if (fs.existsSync(outputPath)) {
        return outputPath;
    }

    let filter = '';
    let inputs = [];
    for (let i = 0; i < repeatCount; i++) {
        const delay = i * intervalMs;
        filter += `[1]adelay=${delay}|${delay}[a${i}]; `;
        inputs.push(`[a${i}]`);
    }
    const joinedInputs = inputs.join('');
    filter += `${joinedInputs}amix=inputs=${repeatCount}[v]; [0][v]amix=inputs=2`;

    const command = `ffmpeg -y -i "${musicPath}" -i "${voicePath}" -filter_complex "${filter}" -map "[v]" "${outputPath}"`;
    execSync(command);

    return outputPath;
}
