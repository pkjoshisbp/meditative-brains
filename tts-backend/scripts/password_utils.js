// Script to generate hashed password or update user password
import mongoose from 'mongoose';
import bcrypt from 'bcrypt';
import User from '../models/User.js';

// Configuration
const MONGO_URI = 'mongodb://pawan:pragati123..@127.0.0.1:27017/motivation';
const SALT_ROUNDS = 10;

async function generateHashedPassword(plainPassword) {
    try {
        const hashedPassword = await bcrypt.hash(plainPassword, SALT_ROUNDS);
        console.log('\n=== PASSWORD HASH GENERATED ===');
        console.log('Plain password:', plainPassword);
        console.log('Hashed password:', hashedPassword);
        console.log('You can copy this hash and paste it directly into the database password field.');
        return hashedPassword;
    } catch (error) {
        console.error('Error generating password hash:', error);
        return null;
    }
}

async function updateUserPassword(username, newPassword) {
    try {
        await mongoose.connect(MONGO_URI, {
            useNewUrlParser: true,
            useUnifiedTopology: true,
        });

        console.log('Connected to MongoDB');

        // Find the user
        const user = await User.findOne({ username });
        if (!user) {
            console.log(`User not found: ${username}`);
            return false;
        }

        // Generate hashed password
        const hashedPassword = await bcrypt.hash(newPassword, SALT_ROUNDS);

        // Update the user's password
        await User.updateOne(
            { username },
            { password: hashedPassword }
        );

        console.log('\n=== PASSWORD UPDATED SUCCESSFULLY ===');
        console.log('Username:', username);
        console.log('New password:', newPassword);
        console.log('Password hash:', hashedPassword);

        await mongoose.disconnect();
        console.log('Disconnected from MongoDB');
        return true;
    } catch (error) {
        console.error('Error updating password:', error);
        await mongoose.disconnect();
        return false;
    }
}

async function verifyPassword(username, testPassword) {
    try {
        await mongoose.connect(MONGO_URI, {
            useNewUrlParser: true,
            useUnifiedTopology: true,
        });

        console.log('Connected to MongoDB');

        const user = await User.findOne({ username });
        if (!user) {
            console.log(`User not found: ${username}`);
            await mongoose.disconnect();
            return false;
        }

        const isValid = await bcrypt.compare(testPassword, user.password);
        
        console.log('\n=== PASSWORD VERIFICATION ===');
        console.log('Username:', username);
        console.log('Test password:', testPassword);
        console.log('Stored hash:', user.password);
        console.log('Password is valid:', isValid);

        await mongoose.disconnect();
        console.log('Disconnected from MongoDB');
        return isValid;
    } catch (error) {
        console.error('Error verifying password:', error);
        await mongoose.disconnect();
        return false;
    }
}

// Main execution
async function main() {
    const args = process.argv.slice(2);
    
    if (args.length === 0) {
        console.log('\n=== PASSWORD UTILITY SCRIPT ===');
        console.log('Usage:');
        console.log('  node scripts/password_utils.js generate <password>');
        console.log('  node scripts/password_utils.js update <username> <new_password>');
        console.log('  node scripts/password_utils.js verify <username> <password>');
        console.log('\nExamples:');
        console.log('  node scripts/password_utils.js generate "pragati123.."');
        console.log('  node scripts/password_utils.js update "pkjoshi@mywebsolutions.co.in" "pragati123.."');
        console.log('  node scripts/password_utils.js verify "pkjoshi@mywebsolutions.co.in" "pragati123.."');
        return;
    }

    const command = args[0];

    switch (command) {
        case 'generate':
            if (args.length < 2) {
                console.log('Error: Please provide a password to hash');
                console.log('Usage: node scripts/password_utils.js generate <password>');
                return;
            }
            await generateHashedPassword(args[1]);
            break;

        case 'update':
            if (args.length < 3) {
                console.log('Error: Please provide username and new password');
                console.log('Usage: node scripts/password_utils.js update <username> <new_password>');
                return;
            }
            await updateUserPassword(args[1], args[2]);
            break;

        case 'verify':
            if (args.length < 3) {
                console.log('Error: Please provide username and password to verify');
                console.log('Usage: node scripts/password_utils.js verify <username> <password>');
                return;
            }
            await verifyPassword(args[1], args[2]);
            break;

        default:
            console.log('Error: Unknown command:', command);
            console.log('Available commands: generate, update, verify');
            break;
    }
}

// Run the script
main().catch(console.error);

export { generateHashedPassword, updateUserPassword, verifyPassword };
