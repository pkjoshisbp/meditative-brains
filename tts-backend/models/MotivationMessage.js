import mongoose from 'mongoose';

const motivationMessageSchema = new mongoose.Schema({
    userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User' }, // Now optional
    categoryId: { type: mongoose.Schema.Types.ObjectId, ref: 'Category', required: true },
    messages: [{ type: String, required: true }],
    editable: { type: Boolean, default: true },
    language: { type: String, default: 'en-US' },
    speaker: { type: String, default: 'en-US-AriaNeural' },
    engine: { type: String, default: 'azure' },
    ssmlMessages: [{ type: String }],
    ssml: [{ type: String }],
    speakerStyle: { type: String },
    speakerPersonality: { type: String }, // New field
    prosodyPitch: { type: String, default: 'medium' }, // New field
    prosodyRate: { type: String, default: 'medium' }, // New field
    prosodyVolume: { type: String, default: 'medium' }, // New field
    audioPaths: [{ type: String }], // Now an array
    audioUrls: [{ type: String }],  // Now an array
});

const MotivationMessage = mongoose.model('MotivationMessage', motivationMessageSchema);
export default MotivationMessage;
