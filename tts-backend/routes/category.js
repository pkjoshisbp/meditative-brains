import express from 'express';
import Category from '../models/Category.js';

const categoryRouter = express.Router();

// POST request to create a new category
categoryRouter.post('/', async (req, res) => {
    try {
        // Add userId to the category if user is authenticated
        const categoryData = {
            ...req.body,
            // Assuming authentication middleware provides req.user
            userId: req.user ? req.user._id : undefined
        };
        
        // Check if user has reached their category limit
        if (req.user && req.user.categoryLimit) {
            const userCategoriesCount = await Category.countDocuments({ userId: req.user._id });
            if (userCategoriesCount >= req.user.categoryLimit) {
                return res.status(403).json({ message: 'You have reached your category limit' });
            }
        }
        
        const newCategory = new Category(categoryData);
        await newCategory.save();
        res.status(201).json(newCategory);
    } catch (error) {
        res.status(400).json({ message: error.message });
    }
});

// GET request to fetch all categories
categoryRouter.get('/', async (req, res) => {
    try {
        // Get current user ID from the authenticated request
        const userId = req.user ? req.user._id : null;
        
        // Find categories that either:
        // 1. Don't have a userId (public/system categories)
        // 2. Have a userId matching the current user
        const categories = await Category.find({
            $or: [
                { userId: { $exists: false } },
                { userId: null },
                { userId: userId }
            ]
        });
        
        res.status(200).json(categories);
    } catch (error) {
        res.status(500).json({ message: error.message });
    }
});

// PUT request to update a category
categoryRouter.put('/:id', async (req, res) => {
    const { id } = req.params;
    try {
        const updatedCategory = await Category.findByIdAndUpdate(id, req.body, { new: true });
        res.status(200).json(updatedCategory);
    } catch (error) {
        res.status(400).json({ message: error.message });
    }
});

// DELETE request to delete a category
categoryRouter.delete('/:id', async (req, res) => {
    const { id } = req.params;
    try {
        await Category.findByIdAndDelete(id);
        res.status(200).json({ message: 'Category deleted successfully' });
    } catch (error) {
        res.status(500).json({ message: error.message });
    }
});

export default categoryRouter;
