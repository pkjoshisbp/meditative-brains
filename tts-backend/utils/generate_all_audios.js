// utils/generate_all_audios.js

import mongoose from 'mongoose';
import dotenv from 'dotenv';
import { generateAudioForMessage } from './audioGenerator.js';
import MotivationMessage from '../models/MotivationMessage.js';

dotenv.config(); // Load .env config

const MONGO_URI = 'mongodb://pawan:pragati123..@127.0.0.1:27017/motivation';

async function run() {
  try {
    await mongoose.connect(MONGO_URI);
    console.log("📦 Connected to MongoDB");

    const messages = await MotivationMessage.find({});
    console.log(`🧠 Found ${messages.length} message sets`);

    let total = 0;

    for (const record of messages) {
      const category = record.category || 'general';
      const language = record.language || 'en-US';
      const speaker = record.speaker || 'en-US-AriaNeural';
      const engine = record.engine || 'azure'; // or 'vits'

      for (const msg of record.messages) {
        try {
          const outputPath = await generateAudioForMessage(msg, {
            engine,
            language,
            speaker,
            speed: 1.1,
            noise: 0.667,
            noiseW: 0.8,
          });
          console.log(`✅ Generated (${engine}): ${msg} → ${outputPath}`);
          total++;
        } catch (err) {
          console.error(`❌ Failed: ${msg}`, err.message);
        }
      }
    }

    console.log(`🎉 All done! ${total} audio files created.`);
  } catch (err) {
    console.error("❌ Connection or processing failed:", err.message);
  } finally {
    mongoose.connection.close();
  }
}

run();
