import mongoose from 'mongoose';
import bcrypt from 'bcrypt';
import User from './models/User.js';

mongoose.connect('mongodb://pawan:pragati123..@127.0.0.1:27017/motivation');

const predefinedUsers = [
  {
    username: 'pawan',
    password: 'pragati123..',
    email: 'info@mywebsolutions.co.in',
    firstName: 'Pawan',
    lastName: 'Joshi',
    phone: '1234567890',
    age: 30,
    country: 'India'
  },
  {
    username: 'pragati',
    password: 'pragati123..',
    email: 'pragati@mywebsolutions.co.in',
    firstName: 'Pragati',
    lastName: 'Joshi',
    phone: '0987654321',
    age: 25,
    country: 'India'
  },
  {
    username: 'saurabh',
    password: 'pragati123..',
    email: 'saurabh@mywebsolutions.co.in',
    firstName: 'Saurabh',
    lastName: 'Joshi',
    phone: '1122334455',
    age: 28,
    country: 'UK'
  },
  {
    username: 'pkjoshi',
    password: 'pragati123..',
    email: 'pkjoshi.sbp@gmail.com',
    firstName: 'Pawan',
    lastName: 'Joshi',
    phone: '6677889900',
    age: 35,
    country: 'Australia'
  }
];

async function seedUsers() {
  try {
    await User.deleteMany({});

    for (const userData of predefinedUsers) {
      const hashedPassword = await bcrypt.hash(userData.password, 10);
      const newUser = new User({ ...userData, password: hashedPassword });
      await newUser.save();
    }

    console.log('Users seeded successfully.');
  } catch (error) {
    console.error('Error seeding users:', error);
  } finally {
    mongoose.connection.close();
  }
}

seedUsers();
