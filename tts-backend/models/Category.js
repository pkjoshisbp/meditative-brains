import mongoose from 'mongoose';

const categorySchema = new mongoose.Schema({
    category: { type: String, required: true },
    userId: { 
        type: mongoose.Schema.Types.ObjectId, 
        ref: 'User',
        // Categories without userId are considered system/global categories
        required: false 
    }
});

// Compound index for unique category names per user
// This allows different users to have the same category name
// But prevents a single user from creating duplicate categories
categorySchema.index({ category: 1, userId: 1 }, { unique: true });

const Category = mongoose.model('Category', categorySchema);
export default Category;
