import Category from '../models/Category.js';

export const createCategory = async (req, res) => {
    try {
        const newCategory = new Category({ category: req.body.category });
        await newCategory.save();
        res.status(201).send(newCategory);
    } catch (error) {
        res.status(400).send(error);
    }
};
