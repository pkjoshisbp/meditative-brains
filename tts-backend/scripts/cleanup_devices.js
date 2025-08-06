// Script to clean up device records and ensure proper database state
import mongoose from 'mongoose';
import Device from '../models/Device.js';
import User from '../models/User.js';

async function cleanupDevices() {
    try {
        // Connect to MongoDB
        await mongoose.connect('mongodb://pawan:pragati123..@127.0.0.1:27017/motivation', {
            useNewUrlParser: true,
            useUnifiedTopology: true,
        });

        console.log('Connected to MongoDB');

        // Remove any devices that don't have valid userId references
        const invalidDevices = await Device.find({});
        let cleanedCount = 0;

        for (const device of invalidDevices) {
            // Check if the referenced user exists
            const user = await User.findById(device.userId);
            if (!user) {
                await Device.deleteOne({ _id: device._id });
                cleanedCount++;
                console.log(`Removed orphaned device: ${device.deviceId}`);
            } else if (!device.username) {
                // Update devices that don't have username field
                await Device.updateOne(
                    { _id: device._id }, 
                    { username: user.username }
                );
                console.log(`Updated device ${device.deviceId} with username: ${user.username}`);
            }
        }

        console.log(`Cleanup completed. Removed ${cleanedCount} orphaned devices.`);
        
        // Show current device registrations
        const devices = await Device.find({}).populate('userId');
        console.log('\nCurrent device registrations:');
        devices.forEach(device => {
            console.log(`User: ${device.username}, Device: ${device.deviceId}, Registered: ${device.createdAt}`);
        });

        await mongoose.disconnect();
        console.log('Disconnected from MongoDB');
    } catch (error) {
        console.error('Error during cleanup:', error);
        process.exit(1);
    }
}

// Uncomment the line below to run the cleanup
// cleanupDevices();

export default cleanupDevices;
