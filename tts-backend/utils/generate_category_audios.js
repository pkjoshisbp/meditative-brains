import mongoose from 'mongoose';
import dotenv from 'dotenv';
import { generateAudioForMessage } from './audioGenerator.js';
import MotivationMessage from '../models/MotivationMessage.js';

dotenv.config();

const MONGO_URI = 'mongodb://pawan:pragati123..@127.0.0.1:27017/motivation';

async function generateForCategory(categoryId, language = 'en-US', speaker = 'en-US-AriaNeural', engine = 'azure') {
    try {
        await mongoose.connect(MONGO_URI);
        console.log("📦 Connected to MongoDB");

        const messages = await MotivationMessage.find({ categoryId });
        console.log(`🧠 Found ${messages.length} message sets for category`);

        let total = 0;

        for (const record of messages) {
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

        console.log(`🎉 Done! ${total} audio files created for category.`);
        return total;
    } catch (err) {
        console.error("❌ Error:", err.message);
        throw err;
    } finally {
        mongoose.connection.close();
    }
}

// If called directly from command line
if (process.argv[2]) {
    const [,, categoryId, language, speaker, engine] = process.argv;
    generateForCategory(categoryId, language, speaker, engine)
        .then(() => process.exit(0))
        .catch(() => process.exit(1));
}

export default generateForCategory;
