import MotivationMessage from '../models/MotivationMessage.js';

export const createMessage = async (req, res) => {
    try {
        const newMessage = new MotivationMessage({
            userId: req.body.userId,
            categoryId: req.body.categoryId,
            messages: req.body.messages
        });
        await newMessage.save();
        res.status(201).send(newMessage);
    } catch (error) {
        res.status(400).send(error);
    }
};
