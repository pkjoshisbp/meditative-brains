import mongoose from 'mongoose';

const chapterSchema = new mongoose.Schema({
    chapterNumber: { type: Number, required: true },
    title: { type: String, default: '' },
    plainContent: { type: String, default: '' },
    ssmlContent: { type: String, default: '' },
    audioPath: { type: String },
    audioUrl: { type: String },
    status: { type: String, enum: ['pending', 'generating', 'done', 'error'], default: 'pending' },
});

const audioBookSchema = new mongoose.Schema({
    bookTitle: { type: String, required: true },
    bookAuthor: { type: String, default: '' },
    language: { type: String, default: 'en-US' },
    speaker: { type: String, default: 'en-US-AriaNeural' },
    engine: { type: String, default: 'azure' },
    speakerStyle: { type: String },
    expressionStyle: { type: String },
    prosodyRate: { type: String, default: 'medium' },
    prosodyPitch: { type: String, default: 'medium' },
    prosodyVolume: { type: String, default: 'medium' },
    chapters: [chapterSchema],
}, { timestamps: true });

const AudioBook = mongoose.model('AudioBook', audioBookSchema);
export default AudioBook;
