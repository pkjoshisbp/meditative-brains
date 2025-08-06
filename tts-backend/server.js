import express from 'express';
import mongoose from 'mongoose';
import path from 'path';
import MotivationMessage from './models/MotivationMessage.js';

const app = express();
const PORT = 3000;

app.use(express.json());
app.use(express.static(path.join(process.cwd(), 'ui/build')));

app.get('/api/categories', async (req, res) => {
    const categories = await MotivationMessage.distinct('category');
    res.json(categories);
});

app.get('/api/messages', async (req, res) => {
    const { category, language } = req.query;
    const messages = await MotivationMessage.find({ category, language });
    res.json(messages);
});

app.post('/api/save-ssml', (req, res) => {
    const { ssml, language, category } = req.body;
    // Save SSML configuration logic here
    res.json({ success: true });
});

app.listen(PORT, () => console.log(`🌐 Server running on http://localhost:${PORT}`));
