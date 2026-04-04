import express from 'express';
import AudioBook from '../models/AudioBook.js';
import { logger } from '../utils/logger.js';

const router = express.Router();

// List all audiobooks
router.get('/', async (req, res) => {
    try {
        const books = await AudioBook.find().sort({ updatedAt: -1 }).select('-chapters.plainContent -chapters.ssmlContent');
        res.json({ success: true, books });
    } catch (e) {
        logger.error('Error listing audiobooks', { error: e.message });
        res.status(500).json({ success: false, error: e.message });
    }
});

// Get single audiobook with full chapter content
router.get('/:id', async (req, res) => {
    try {
        const book = await AudioBook.findById(req.params.id);
        if (!book) return res.status(404).json({ success: false, error: 'Audiobook not found' });
        res.json({ success: true, book });
    } catch (e) {
        logger.error('Error fetching audiobook', { error: e.message });
        res.status(500).json({ success: false, error: e.message });
    }
});

// Create or update (upsert by bookTitle)
router.post('/', async (req, res) => {
    try {
        const { bookTitle, bookAuthor, language, speaker, engine, speakerStyle, expressionStyle,
                prosodyRate, prosodyPitch, prosodyVolume, chapters } = req.body;

        if (!bookTitle) {
            return res.status(400).json({ success: false, error: 'bookTitle is required' });
        }

        const chaptersData = (chapters || []).map((ch, i) => ({
            chapterNumber: ch.chapterNumber ?? i + 1,
            title: ch.title || '',
            plainContent: ch.plainContent || ch.plain_content || '',
            ssmlContent: ch.ssmlContent || ch.ssml_content || '',
            audioPath: ch.audioPath || ch.audio_path || '',
            audioUrl: ch.audioUrl || ch.audio_url || '',
            status: ch.status || 'pending',
        }));

        let book = await AudioBook.findOne({ bookTitle });
        if (book) {
            book.bookAuthor = bookAuthor || book.bookAuthor;
            book.language = language || book.language;
            book.speaker = speaker || book.speaker;
            book.engine = engine || book.engine;
            book.speakerStyle = speakerStyle ?? book.speakerStyle;
            book.expressionStyle = expressionStyle ?? book.expressionStyle;
            book.prosodyRate = prosodyRate || book.prosodyRate;
            book.prosodyPitch = prosodyPitch || book.prosodyPitch;
            book.prosodyVolume = prosodyVolume || book.prosodyVolume;
            book.chapters = chaptersData;
            await book.save();
            logger.info('Updated audiobook', { bookTitle, chapterCount: chaptersData.length });
        } else {
            book = new AudioBook({
                bookTitle, bookAuthor, language, speaker, engine,
                speakerStyle, expressionStyle,
                prosodyRate, prosodyPitch, prosodyVolume,
                chapters: chaptersData
            });
            await book.save();
            logger.info('Created audiobook', { bookTitle, chapterCount: chaptersData.length });
        }

        res.json({ success: true, book });
    } catch (e) {
        logger.error('Error saving audiobook', { error: e.message });
        res.status(500).json({ success: false, error: e.message });
    }
});

// Update a single chapter
router.put('/:id/chapters/:chapterNumber', async (req, res) => {
    try {
        const book = await AudioBook.findById(req.params.id);
        if (!book) return res.status(404).json({ success: false, error: 'Audiobook not found' });

        const chNum = parseInt(req.params.chapterNumber, 10);
        const chapter = book.chapters.find(c => c.chapterNumber === chNum);
        if (!chapter) return res.status(404).json({ success: false, error: 'Chapter not found' });

        const { title, plainContent, ssmlContent, audioPath, audioUrl, status } = req.body;
        if (title !== undefined) chapter.title = title;
        if (plainContent !== undefined) chapter.plainContent = plainContent;
        if (ssmlContent !== undefined) chapter.ssmlContent = ssmlContent;
        if (audioPath !== undefined) chapter.audioPath = audioPath;
        if (audioUrl !== undefined) chapter.audioUrl = audioUrl;
        if (status !== undefined) chapter.status = status;

        await book.save();
        res.json({ success: true, chapter });
    } catch (e) {
        logger.error('Error updating chapter', { error: e.message });
        res.status(500).json({ success: false, error: e.message });
    }
});

// Delete audiobook
router.delete('/:id', async (req, res) => {
    try {
        const book = await AudioBook.findByIdAndDelete(req.params.id);
        if (!book) return res.status(404).json({ success: false, error: 'Audiobook not found' });
        res.json({ success: true, message: 'Audiobook deleted' });
    } catch (e) {
        logger.error('Error deleting audiobook', { error: e.message });
        res.status(500).json({ success: false, error: e.message });
    }
});

// Flutter endpoint: get audiobook with chapters (text + audio) for playback
router.get('/:id/playback', async (req, res) => {
    try {
        const book = await AudioBook.findById(req.params.id);
        if (!book) return res.status(404).json({ success: false, error: 'Audiobook not found' });

        const playbackData = {
            bookTitle: book.bookTitle,
            bookAuthor: book.bookAuthor,
            chapters: book.chapters
                .filter(ch => ch.status === 'done' && ch.audioUrl)
                .sort((a, b) => a.chapterNumber - b.chapterNumber)
                .map(ch => ({
                    chapterNumber: ch.chapterNumber,
                    title: ch.title,
                    text: ch.plainContent || ch.ssmlContent,
                    audioUrl: ch.audioUrl,
                    audioPath: ch.audioPath,
                })),
        };

        res.json({ success: true, ...playbackData });
    } catch (e) {
        logger.error('Error fetching playback data', { error: e.message });
        res.status(500).json({ success: false, error: e.message });
    }
});

export default router;
