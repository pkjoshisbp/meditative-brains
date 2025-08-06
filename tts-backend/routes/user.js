import express from 'express';
import User from '../models/User.js';
import Device from '../models/Device.js';
import checkDevice from '../middleware/checkDevice.js';
import jwt from 'jsonwebtoken';
import bcrypt from 'bcryptjs';
import crypto from 'crypto';
import nodemailer from 'nodemailer';


const userRouter = express.Router();

function generateRandomPassword(length = 12) {
    return crypto.randomBytes(length).toString('hex').slice(0, length);
}
// Configure Nodemailer
const transporter = nodemailer.createTransport({
    host: 'server1.mywebsolutions.co.in', // e.g., smtp.gmail.com
    port: 587, // or 465 for secure SMTP
    secure: false, // true for port 465, false for other ports
    auth: {
        user: 'info@mywebsolutions.co.in', // Your email
        pass: 'pragati123..', // Your email password
    },
});

// POST request to create a new user
userRouter.post('/', async (req, res) => {
    try {
        const newUser = new User(req.body);
        await newUser.save();
        res.status(201).json(newUser);
    } catch (error) {
        res.status(400).json({ message: error.message });
    }
});
userRouter.post('/register', async (req, res) => {
    console.log('register req.body',req.body)
    const { username, email, firstName, lastName } = req.body;

    // Generate a random password
    const password = generateRandomPassword();

    try {
        const hashedPassword = await bcrypt.hash(password, 10);
        const newUser = new User({ username, password: hashedPassword, email, firstName, lastName });
        await newUser.save();

        // Send email to the user
        const mailOptions = {
            from: 'info@mywebsolutions.co.in',
            to: email,
            subject: 'Your New Account Credentials',
            text: `Hello ${firstName},\n\nYour account has been created. Here are your login details:\n\nUsername: ${username}\nPassword: ${password}\n\nPlease change your password after logging in for the first time.\n\nBest regards,\nYour Company`,
        };

        transporter.sendMail(mailOptions, (error, info) => {
            if (error) {
                return console.error('Error sending email:', error);
            }
            console.log('Email sent:', info.response);
        });

        res.status(201).json({ message: 'User registered successfully' });
    } catch (error) {
        res.status(500).json({ message: error.message });
    }
});
// User login
/*
userRouter.post('/login', checkDevice, async (req, res) => {
  console.log('req',req.body);
  const { username, password, deviceId } = req.body;

  try {
      const user = await User.findOne({ username });
      if (!user) {
          return res.status(400).json({ message: 'Invalid username or password' });
      }

      const isPasswordValid = await bcrypt.compare(password, user.password);
      if (!isPasswordValid) {
          return res.status(400).json({ message: 'Invalid username or password' });
      }

      const token = jwt.sign({ userId: user._id }, secretKey, { expiresIn: '1h' });

      // Register device
      const newDevice = new Device({ userId: user._id, deviceId });
      await newDevice.save();

      res.status(200).json({ token });
  } catch (error) {
      res.status(500).json({ message: error.message });
  }
});
*/
// GET request to fetch all users
userRouter.get('/', async (req, res) => {
    try {
        const users = await User.find();
        res.status(200).json(users);
    } catch (error) {
        res.status(500).json({ message: error.message });
    }
});

// PUT request to update a user
userRouter.put('/:id', async (req, res) => {
    const { id } = req.params;
    try {
        const updatedUser = await User.findByIdAndUpdate(id, req.body, { new: true });
        res.status(200).json(updatedUser);
    } catch (error) {
        res.status(400).json({ message: error.message });
    }
});

// DELETE request to delete a user
userRouter.delete('/:id', async (req, res) => {
    const { id } = req.params;
    try {
        await User.findByIdAndDelete(id);
        res.status(200).json({ message: 'User deleted successfully' });
    } catch (error) {
        res.status(500).json({ message: error.message });
    }
});

export default userRouter;
