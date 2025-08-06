import express from 'express';
import Language from '../models/Language.js';

const router = express.Router();

// Get all languages
router.get('/', async (req, res) => {
  try {
    const languages = await Language.find();
    res.json(languages);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Add a new language
router.post('/', async (req, res) => {
  try {
    const language = new Language(req.body);
    await language.save();
    res.status(201).json(language);
  } catch (err) {
    res.status(400).json({ error: err.message });
  }
});

export default router;
