import express from 'express';
import crypto from 'crypto';
import MotivationMessage from '../models/MotivationMessage.js';
import authMiddleware from './auth.js';
import mongoose from 'mongoose';
import { logger } from '../utils/logger.js';
import fs from 'fs';
import path from 'path';
import { generateAudioForMessage } from '../utils/audioGenerator.js';

const motivationMessageRouter = express.Router();
motivationMessageRouter.use(authMiddleware);

// POST request to create a new message
motivationMessageRouter.post('/', async (req, res) => {
  logger.info('POST /api/motivationMessage - Incoming request', { body: req.body });
  const {
    categoryId,
    userId,
    messages,
    ssmlMessages,
    ssml,
    engine,
    language,
    speaker,
    speakerStyle,
    speakerPersonality,
    prosodyRate,
    prosodyPitch,
    prosodyVolume
  } = req.body;

  try {
    // Normalize language code
    const formattedLanguage = language === 'hn-IN' ? 'hi-IN' : language.replace('_', '-');
    
    // Always use AvaMultilingualNeural for supported languages
    const selectedSpeaker = ['en-IN', 'en-US', 'hi-IN', 'hn-IN'].includes(formattedLanguage) 
      ? 'en-US-AvaMultilingualNeural'
      : (speaker || selectAppropriateVoice(formattedLanguage));

    const recordData = {
      categoryId,
      messages: Array.isArray(messages) ? messages : messages.split('$$'),
      ssmlMessages,
      ssml: Array.isArray(ssml) ? ssml : [],
      engine,
      language: formattedLanguage,
      speaker: selectedSpeaker,
      speakerStyle,
      speakerPersonality,
      prosodyRate,
      prosodyPitch,
      prosodyVolume,
      audioPaths: [], // No audio generated here
      audioUrls: []   // No audio generated here
    };
    if (userId) recordData.userId = userId;
    const record = new MotivationMessage(recordData);
    await record.save();
    logger.info('Motivation message saved successfully', { userId, categoryId });
    console.log('Motivation message saved successfully', { userId, categoryId });
    res.status(200).json({ success: true, message: "Saved successfully" });
  } catch (err) {
    logger.error('Error saving motivation message', { error: err.message, stack: err.stack });
    console.log('Error saving motivation message', { error: err.message, stack: err.stack });
    res.status(500).json({ success: false, error: err.message });
  }
});

// GET request to fetch all messages
motivationMessageRouter.get('/', async (req, res) => {
  try {
    const messages = await MotivationMessage.find().populate('userId').populate('categoryId');
    logger.info('Fetched all motivation messages', { count: messages.length });
    console.log('Fetched all motivation messages', { count: messages.length });
    res.status(200).json(messages);
  } catch (error) {
    logger.error('Error fetching all motivation messages', { error: error.message, stack: error.stack });
    console.log('Error fetching all motivation messages', { error: error.message, stack: error.stack });
    res.status(500).json({ message: error.message });
  }
});

motivationMessageRouter.get('/admin-only', async (req, res) => {
  try {
    const messages = await MotivationMessage.find({
      userId: { $exists: false }
    }).populate('categoryId');
    logger.info('Fetched admin-only motivation messages', { count: messages.length });
    console.log('Fetched admin-only motivation messages', { count: messages.length });
    res.status(200).json(messages);
  } catch (error) {
    logger.error('Error fetching admin-only motivation messages', { error: error.message, stack: error.stack });
    console.log('Error fetching admin-only motivation messages', { error: error.message, stack: error.stack });
    res.status(500).json({ message: error.message });
  }
});

motivationMessageRouter.get('/category/:categoryId', async (req, res) => {
  const { categoryId } = req.params;
  try {
    const messages = await MotivationMessage.find({ categoryId }).populate('userId').populate('categoryId');
    logger.info('Fetched motivation messages by category', { categoryId, count: messages.length });
    console.log('Fetched motivation messages by category', { categoryId, count: messages.length });
    res.status(200).json(messages);
  } catch (error) {
    logger.error('Error fetching motivation messages by category', { error: error.message, stack: error.stack, categoryId });
    console.log('Error fetching motivation messages by category', { error: error.message, stack: error.stack, categoryId });
    res.status(500).json({ message: error.message });
  }
});

// Generate audio for all messages in a category
motivationMessageRouter.post('/generate-category-audio', async (req, res) => {
  logger.info('POST /api/motivationMessage/generate-category-audio - Incoming request', { body: req.body });
  console.log('Incoming request to /generate-category-audio', { body: req.body });
  const {
    categoryId,
    language,
    speaker,
    engine,
    speakerStyle,
    speakerPersonality,
    rate,
    pitch,
    volume
  } = req.body;
  
  try {
    const category = await mongoose.model('Category')
      .findById(categoryId)
      .select('category')
      .lean();
   // const categoryName = category && typeof category.category === 'string' ? category.category : 'default';
    const categoryName = category;
    
    logger.info('categoryName to be used', { categoryName });
    console.log('categoryName to be used', { categoryName });
    if (!category) throw new Error('Category not found');
    const messages = await MotivationMessage.find({ categoryId });
    let filesGenerated = 0, errors = [];
    for (const message of messages) {
      try {
        let audioPaths = [];
        let audioUrls = [];
        for (let i = 0; i < message.messages.length; i++) {
          const text = message.messages[i];
          const ssmlToUse = Array.isArray(message.ssml) ? message.ssml[i] : undefined;
          logger.info('Audio generation: message, ssml', {
            messageIndex: i,
            text,
            ssml: ssmlToUse
          });
          const audioOptions = {
            engine,
            language,
            speaker,
            speakerStyle: speakerStyle || 'empathetic',
            speakerPersonality,
            category: categoryName.category,
            rate, pitch, volume,
            noise: 0.667,
            noiseW: 0.8,
            ssml: ssmlToUse // Use SSML from DB if present
          };
          const { relativePath, audioUrl } = await generateAudioForMessage(text, audioOptions);
          audioPaths.push(relativePath);
          audioUrls.push(audioUrl);
        }
        await MotivationMessage.findByIdAndUpdate(
          message._id,
          { $set: { audioPaths, audioUrls, language, speaker, engine } },
          { new: true }
        );
        filesGenerated++;
      } catch (err) {
        const error = `Error processing message ${message._id}: ${err.message}`;
        logger.error(error);
        console.log(error);
        errors.push(error);
      }
    }
    res.json({ success: true, filesGenerated, errors: errors.length ? errors : undefined, categoryName: categoryName });
  } catch (error) {
    logger.error('Audio generation failed', { error: error.message, stack: error.stack });
    console.log('Audio generation failed', { error: error.message, stack: error.stack });
    res.status(500).json({ success: false, error: error.message });
  }
});

// Generate audio for a single message by recordId (for Laravel GET request)
motivationMessageRouter.get('/generate-category-audio/:recordId', async (req, res) => {
  logger.info('Incoming request to /generate-category-audio/:recordId', { params: req.params });
  console.log('Incoming request to /generate-category-audio/:recordId', { params: req.params });
  const { recordId } = req.params;
  try {
    const message = await MotivationMessage.findById(recordId).populate('categoryId');
    if (!message) {
      logger.error('Motivation message not found', { recordId });
      console.log('Motivation message not found', { recordId });
      return res.status(404).json({ success: false, message: 'Message not found' });
    }
    // Get category name string
    let categoryName = 'default';
    if (message.categoryId && typeof message.categoryId.category === 'string') {
      categoryName = message.categoryId.category;
    }
    // --- FIX: initialize arrays ---
    let audioPaths = [];
    let audioUrls = [];
    // --- END FIX ---
    let filesGenerated = 0, errors = [];
    for (let i = 0; i < message.messages.length; i++) {
      const text = message.messages[i];
      try {
        if (typeof text !== 'string') continue;
        const audioOptions = {
          engine: message.engine,
          language: message.language,
          speaker: message.speaker,
          speakerStyle: message.speakerStyle || 'empathetic',
          speakerPersonality: message.speakerPersonality,
          category: categoryName,
          rate: message.rate,
          pitch: message.pitch,
          volume: message.volume,
          noise: 0.667,
          noiseW: 0.8,
          ssml: Array.isArray(message.ssml) ? message.ssml[i] : undefined // Use SSML from DB if present
        };
        const { relativePath, audioUrl } = await generateAudioForMessage(text, audioOptions);
        audioPaths.push(relativePath);
        audioUrls.push(audioUrl);
      } catch (err) {
        const error = `Error processing message ${message._id}: ${err.message}`;
        logger.error(error);
        console.log(error);
        errors.push(error);
      }
    }
    // --- FIX: update DB with audioPaths/audioUrls ---
    await MotivationMessage.findByIdAndUpdate(
      recordId,
      { $set: { audioPaths, audioUrls } },
      { new: true }
    );
    // --- END FIX ---
    res.json({ success: true, filesGenerated, errors: errors.length ? errors : undefined, recordId });
  } catch (error) {
    logger.error('Audio generation failed', { error: error.message, stack: error.stack, recordId });
    console.log('Audio generation failed', { error: error.message, stack: error.stack, recordId });
    res.status(500).json({ success: false, error: error.message });
  }
});

// PUT request to update a message
motivationMessageRouter.put('/:id', async (req, res) => {
  logger.info('PUT handler started', { params: req.params });
  logger.info('PUT /api/motivationMessage/:id - Incoming request', { params: req.params, body: req.body });
  const { id } = req.params;
  const { categoryId, messages, ssmlMessages, ssml, engine, language, prosodyRate, prosodyPitch, prosodyVolume, speaker, speakerStyle, speakerPersonality, audioPath } = req.body;
  try {
    const category = await mongoose.model('Category')
      .findById(categoryId)
      .select('category')
      .lean() || { category: 'default' };
    const categoryName = category && typeof category.category === 'string' ? category.category : 'default';
    logger.debug('Category lookup result', {
      categoryId,
      foundCategory: categoryName
    });
    console.log('Category lookup result', {
      categoryId,
      foundCategory: categoryName
    });

    // No audio generation here
    const messageArray = Array.isArray(messages) ? messages : messages.split('$$');
    const updateData = {
      messages: messageArray,
      categoryId: category._id,
      ssmlMessages,
      ssml: Array.isArray(ssml) ? ssml : [],
      engine,
      language,
      speaker,
      speakerStyle,
      speakerPersonality,
      prosodyRate,
      prosodyPitch,
      prosodyVolume,
      audioPaths: [], // Clear or leave as is, but do not generate
      audioUrls: []
    };
    const updatedMessage = await MotivationMessage.findByIdAndUpdate(
      id,
      { $set: updateData },
      { new: true }
    ).populate('categoryId');
    logger.info('Update result', { updatedMessage });
    if (!updatedMessage) {
      logger.error('Message not found', { id });
      console.log('Message not found', { id });
      return res.status(404).json({ success: false, message: 'Message not found' });
    }
    logger.info('Message updated successfully', {
      messageId: id,
      categoryName: categoryName,
      audioPaths: [] // No audio generated
    });
    console.log('Message updated successfully', {
      messageId: id,
      categoryName: categoryName,
      audioPaths: []
    });
    res.status(200).json({
      success: true,
      message: 'Updated successfully',
      data: updatedMessage
    });
    logger.info('PUT handler finished', { id });
  } catch (error) {
    logger.error('Error updating message', {
      error: error.message,
      stack: error.stack,
      id,
      categoryId
    });
    console.log('Error updating message', {
      error: error.message,
      stack: error.stack,
      id,
      categoryId
    });
    res.status(500).json({ success: false, error: error.message });
  }
});

// GET messages by language and categoryId (flat array for frontend)
motivationMessageRouter.get('/language/:language/category/:categoryId', async (req, res) => {
  const { language, categoryId } = req.params;
  try {
    let messages = await MotivationMessage.find({ language, categoryId })
      .populate('userId')
      .populate('categoryId')
      .lean();

    // Flatten to one object per message with corresponding audioPath/audioUrl, and exclude ssml/ssmlMessages
    const flatMessages = [];
    for (const msg of messages) {
      const { messages: texts = [], audioPaths = [], audioUrls = [], ssml, ssmlMessages, ...rest } = msg;
      for (let i = 0; i < texts.length; i++) {
        flatMessages.push({
          ...rest,
          text: texts[i],
          audioPath: audioPaths[i] || null,
          audioUrl: audioUrls[i] || null
        });
      }
    }

    logger.info('Fetched motivation messages by language and category (flat)', { language, categoryId, count: flatMessages.length });
    console.log('Fetched motivation messages by language and category (flat)', { language, categoryId, count: flatMessages.length });
    res.status(200).json(flatMessages);
  } catch (error) {
    logger.error('Error fetching motivation messages by language and category', { error: error.message, stack: error.stack, language, categoryId });
    console.log('Error fetching motivation messages by language and category', { error: error.message, stack: error.stack, language, categoryId });
    res.status(500).json({ message: error.message });
  }
});

// Utility to compute hash
function computeMessageHash(text, ssml) {
  return crypto.createHash('sha256').update((text || '') + (ssml || '')).digest('hex');
}

// Generate audio for a specific message in a document, with hash check
motivationMessageRouter.get('/generate-message-audio/:recordId/:messageIndex', async (req, res) => {
  const { recordId, messageIndex } = req.params;
  try {
    const messageDoc = await MotivationMessage.findById(recordId);
    if (!messageDoc) return res.status(404).json({ success: false, message: 'Document not found' });

    const idx = parseInt(messageIndex, 10);
    if (!Array.isArray(messageDoc.messages) || idx < 0 || idx >= messageDoc.messages.length)
      return res.status(400).json({ success: false, message: 'Invalid message index' });

    const text = messageDoc.messages[idx];
    const ssml = Array.isArray(messageDoc.ssml) ? messageDoc.ssml[idx] : undefined;
    const hash = computeMessageHash(text, ssml);

    // Ensure hashes array exists
    if (!Array.isArray(messageDoc.audioHashes)) messageDoc.audioHashes = [];

    // Check if hash matches and file exists
    if (
      messageDoc.audioHashes[idx] === hash &&
      Array.isArray(messageDoc.audioPaths) &&
      messageDoc.audioPaths[idx] &&
      fs.existsSync(path.join(process.cwd(), messageDoc.audioPaths[idx]))
    ) {
      return res.json({ success: true, skipped: true, message: 'Audio already exists and matches hash.' });
    }

    // Generate audio
    const audioOptions = {
      engine: messageDoc.engine,
      language: messageDoc.language,
      speaker: messageDoc.speaker,
      speakerStyle: messageDoc.speakerStyle || 'empathetic',
      speakerPersonality: messageDoc.speakerPersonality,
      category: messageDoc.categoryId?.category || 'default',
      rate: messageDoc.rate,
      pitch: messageDoc.pitch,
      volume: messageDoc.volume,
      noise: 0.667,
      noiseW: 0.8,
      ssml
    };
    const { relativePath, audioUrl } = await generateAudioForMessage(text, audioOptions);

    // Update arrays
    if (!Array.isArray(messageDoc.audioPaths)) messageDoc.audioPaths = [];
    if (!Array.isArray(messageDoc.audioUrls)) messageDoc.audioUrls = [];
    messageDoc.audioPaths[idx] = relativePath;
    messageDoc.audioUrls[idx] = audioUrl;
    messageDoc.audioHashes[idx] = hash;
    await messageDoc.save();

    res.json({ success: true, generated: true, audioPath: relativePath, audioUrl });
  } catch (error) {
    logger.error('Error generating audio for specific message', { error: error.message, stack: error.stack, recordId, messageIndex });
    res.status(500).json({ success: false, error: error.message });
  }
});

// POST endpoint to preview SSML formatted messages
motivationMessageRouter.post('/preview-ssml', async (req, res) => {
  logger.info('POST /api/motivationMessage/preview-ssml - Incoming request', { body: req.body });
  try {
    const { messages } = req.body;
    
    if (!messages || !Array.isArray(messages)) {
      return res.status(400).json({ 
        success: false, 
        error: 'Messages array is required' 
      });
    }

    // Transform messages to SSML format
    const ssmlFormattedMessages = messages.map((message, index) => {
      return transformToSSML(message, index);
    });

    logger.info('Successfully transformed messages to SSML format', { 
      originalCount: messages.length, 
      ssmlCount: ssmlFormattedMessages.length 
    });

    res.status(200).json({ 
      success: true, 
      ssmlMessages: ssmlFormattedMessages 
    });
  } catch (error) {
    logger.error('Error transforming messages to SSML', { 
      error: error.message, 
      stack: error.stack 
    });
    res.status(500).json({ 
      success: false, 
      error: error.message 
    });
  }
});

// Helper function to transform regular messages to SSML format
function transformToSSML(message, index = 0) {
  if (!message || typeof message !== 'string') {
    return '';
  }

  // Add intro for the first message of each section
  let ssmlMessage = '';
  
  if (index === 0) {
    ssmlMessage += `[personality:Caring][rate:-30%]Welcome to this **powerful journey** of transformation.[/personality] [pause:1000] Take a moment now to find a comfortable position where you can *fully relax*. [silence:2000]\n\n`;
  }

  // Transform the message with SSML markup
  const transformedMessage = message
    // Add emphasis to key confidence words
    .replace(/\b(confidence|confident|powerful|strength|strong|success|successful)\b/gi, '**$1**')
    .replace(/\b(believe|faith|trust|hope|courage|determination|resolve)\b/gi, '*$1*')
    
    // Add personality and rate variations
    .replace(/^([^.!?]*[.!?])/, '[personality:Caring][rate:-10%]$1[/personality]')
    
    // Add pauses after sentences
    .replace(/([.!?])\s+/g, '$1 [pause:800] ')
    
    // Add longer pauses after sections
    .replace(/([.!?])\s*$/g, '$1 [silence:1500]');

  // Randomly add personality variations for variety
  const personalities = ['Caring', 'Pleasant', 'Friendly'];
  const rates = ['-10%', '-15%', '-20%', 'slow'];
  
  const randomPersonality = personalities[Math.floor(Math.random() * personalities.length)];
  const randomRate = rates[Math.floor(Math.random() * rates.length)];
  
  // Wrap with random personality and rate
  ssmlMessage += `[personality:${randomPersonality}][rate:${randomRate}]${transformedMessage}[/personality]`;

  return ssmlMessage;
}

export default motivationMessageRouter;
