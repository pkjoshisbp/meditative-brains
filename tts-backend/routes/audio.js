import express from 'express';
import { generateAudioForMessage } from '../utils/audioGenerator.js';
import { mergeVoiceWithMusic } from '../utils/mergeAudio.js';
import path from 'path';

const router = express.Router();

router.post('/generate', async (req, res) => {
    const { message, repeat = 3, interval = 10000, background = "calm.mp3" } = req.body;

    if (!message) return res.status(400).json({ error: "Message is required" });

    try {
        const voicePath = generateAudioForMessage(message);

        const musicPath = path.join(process.cwd(), 'bg-music', background); // e.g. calm.mp3 in /bg-music/
        const finalAudioPath = mergeVoiceWithMusic(voicePath, musicPath, repeat, interval);

        res.json({ path: finalAudioPath.replace(process.cwd(), '') });
    } catch (err) {
        console.error("Audio generation failed:", err);
        res.status(500).json({ error: "Internal Server Error" });
    }
});

export default router;
