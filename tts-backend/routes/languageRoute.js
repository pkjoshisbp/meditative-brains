import express from 'express';
import Language from '../models/Language.js';
import { logger } from '../utils/logger.js';

const languageRouter = express.Router();

// Get all active languages
languageRouter.get('/', async (req, res) => {
  try {
    const languages = await Language.find({ isActive: true })
      .sort({ name: 1 })
      .select('-__v');
    res.status(200).json(languages);
  } catch (error) {
    logger.error('Error fetching languages', { error: error.message });
    res.status(500).json({ message: error.message });
  }
});

// Add a new language (admin only)
languageRouter.post('/', async (req, res) => {
  try {
    const language = new Language(req.body);
    await language.save();
    res.status(201).json(language);
  } catch (error) {
    logger.error('Error creating language', { error: error.message });
    res.status(400).json({ message: error.message });
  }
});

export default languageRouter;
