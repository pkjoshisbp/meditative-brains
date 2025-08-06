import express from 'express';
import { generateAudioForMessage } from '../utils/audioGenerator.js';
import { logger } from '../utils/logger.js';

const router = express.Router();

// Dedicated SSML builder for attention guide audio, with prosody rate
function buildAttentionGuideSSML({ text, language, speaker, speed }) {
  // Default to "medium" if speed is not provided
  const prosodyRate = speed ? speed : 'medium';
  return `<?xml version="1.0"?>
<speak version="1.0"
       xmlns="http://www.w3.org/2001/10/synthesis"
       xmlns:mstts="http://www.w3.org/2001/mstts"
       xmlns:emo="http://www.w3.org/2009/10/emotionml"
       xml:lang="${language}">
  <voice name="${speaker}">
    <mstts:express-as role="assistant">
      <lang xml:lang="${language}">
        <prosody rate="${prosodyRate}">${text}</prosody>
      </lang>
    </mstts:express-as>
  </voice>
</speak>`;
}

router.post('/audio', async (req, res) => {
  // Log the full request from Flutter app
  logger.info('Attention guide audio request received', req.body);

  const { text, language, speaker, engine, speakerStyle, category, speed } = req.body;
  try {
    // Always use "attention-guide" as category unless overridden
    const audioCategory = category || 'attention-guide';

    // Build SSML dynamically for attention guide, including speed
    const ssml = buildAttentionGuideSSML({ text, language, speaker, speed });

    const { relativePath, audioUrl } = await generateAudioForMessage(text, {
      language,
      speaker,
      engine,
      speakerStyle,
      category: audioCategory,
      speakerPersonality: 'assistant',
      ssml // Pass generated SSML directly
    });

    logger.info('Generated attention guide audio', { audioUrl, relativePath });
    res.json({
      success: true,
      audioUrl,
      relativePath,
      message: 'Attention guide audio generated successfully'
    });
  } catch (e) {
    logger.error('Error generating attention guide audio', { error: e.message, stack: e.stack });
    res.status(500).json({ success: false, error: e.message });
  }
});

export default router;
