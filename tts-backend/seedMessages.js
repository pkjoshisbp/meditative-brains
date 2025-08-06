// seedMessages.js
import mongoose from 'mongoose';
import MotivationMessage from './models/MotivationMessage.js';
import Category from './models/Category.js';
import { predefinedMessages } from './predefinedMessages.js';

mongoose.connect('mongodb://pawan:pragati123..@127.0.0.1:27017/motivation');

async function seedMessages() {
  try {
    await MotivationMessage.deleteMany({ editable: false });

    const messagesToInsert = await Promise.all(predefinedMessages.map(async (msg) => {
      const category = await Category.findOne({ category: msg.categoryName });
      if (category) {
        return {
          categoryId: category._id,
          messages: msg.messages,
          editable: msg.editable
        };
      }
      return null;
    }));

    const filteredMessages = messagesToInsert.filter(msg => msg !== null);

    const insertedMessages = await MotivationMessage.insertMany(filteredMessages);
    console.log('Messages seeded successfully:', insertedMessages);
  } catch (error) {
    console.error('Error seeding messages:', error);
  } finally {
    mongoose.connection.close();
  }
}

seedMessages();
