import mongoose from 'mongoose';

const ttsProductSchema = new mongoose.Schema({
    // Laravel product ID reference
    laravel_product_id: { 
        type: Number, 
        required: true,
        unique: true 
    },
    
    // Product metadata
    title: { type: String, required: true },
    description: { type: String },
    category: { type: String, required: true },
    
    // TTS content
    messages: [{ type: String, required: true }],
    
    // TTS configuration
    language: { type: String, default: 'en-US' },
    voice: { type: String, default: 'en-US-AriaNeural' },
    engine: { type: String, default: 'azure' },
    
    // Prosody settings
    prosodyRate: { type: String, default: 'medium' },
    prosodyPitch: { type: String, default: 'medium' },
    prosodyVolume: { type: String, default: 'medium' },
    speakerStyle: { type: String },
    
    // Generated audio
    audioPaths: [{ type: String }],
    audioUrls: [{ type: String }],
    previewPath: { type: String },
    previewUrl: { type: String },
    
    // Background music settings (for reference, mixing handled by Flutter)
    backgroundMusicCategory: { type: String },
    backgroundMusicFile: { type: String },
    
    // Metadata
    totalDuration: { type: Number }, // estimated duration in seconds
    audioGenerated: { type: Boolean, default: false },
    lastGenerated: { type: Date },
    
    // Status
    status: { 
        type: String, 
        enum: ['draft', 'ready', 'generating', 'error'], 
        default: 'draft' 
    },
    errorMessage: { type: String }
    
}, {
    timestamps: true
});

// Indexes
ttsProductSchema.index({ laravel_product_id: 1 });
ttsProductSchema.index({ category: 1 });
ttsProductSchema.index({ status: 1 });
ttsProductSchema.index({ audioGenerated: 1 });

const TtsProduct = mongoose.model('TtsProduct', ttsProductSchema);
export default TtsProduct;
