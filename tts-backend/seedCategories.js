import mongoose from 'mongoose';
import Category from './models/Category.js';
import { predefinedCategories } from './predefinedCategories.js';
import fs from 'fs';

mongoose.connect('mongodb://pawan:pragati123..@127.0.0.1:27017/motivation');

async function seedCategories() {
  try {
    await Category.deleteMany({});
    const insertedCategories = await Category.insertMany(predefinedCategories);
    console.log('Categories seeded successfully:', insertedCategories);

    const categoriesToSave = insertedCategories.map(cat => ({ _id: cat._id, category: cat.category }));
    fs.writeFileSync('categories.json', JSON.stringify(categoriesToSave, null, 2));
  } catch (error) {
    console.error('Error seeding categories:', error);
  } finally {
    mongoose.connection.close();
  }
}

seedCategories();
