import express from 'express';
import generateForCategory from '../utils/generate_category_audios.js';

const router = express.Router();

// Comment out or remove this route to let motivationMessageRouter handle it
// router.post('/api/generate-category-audio', async (req, res) => {
//     const { categoryId, language, speaker, engine } = req.body;

//     if (!categoryId) {
//         return res.status(400).json({ error: 'Category ID is required' });
//     }

//     try {
//         // Start the generation process
//         const filesGenerated = await generateForCategory(categoryId, language, speaker, engine);
        
//         res.json({
//             success: true,
//             filesGenerated,
//             message: `Audio generation completed: ${filesGenerated} files generated`
//         });
//     } catch (error) {
//         console.error('Audio generation error:', error);
//         res.status(500).json({
//             success: false,
//             error: error.message
//         });
//     }
// });

export default router;
