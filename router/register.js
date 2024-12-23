const express = require('express');
const crypto = require('crypto');
const argon2 = require('argon2');
const helmet = require('helmet');
const { body, validationResult } = require('express-validator');
const {
    validateUsername,
    validatePassword,
    validateEmail,
    validateNickname,
    checkUsernameExists,
    checkEmailExists,
    checkNicknameExists
} = require('../validation');
const { logActivity, convertDurationToMilliseconds } = require('../utils');
const { mail, notifyAdmins } = require('../modules/notificator');
const { User, Licenses } = require('../models');
const { RateLimiter } = require('../modules/rateLimiter');

const router = express.Router();

router.use(helmet({
    contentSecurityPolicy: {
        directives: {
            defaultSrc: ["'self'"],
            scriptSrc: ["'self'"],
            objectSrc: ["'none'"],
            upgradeInsecureRequests: [],
        },
    },
}));

const validateInputs = async ({ username, nickname, password, email }) => {
    const validations = await Promise.all([
        validateUsername(username),
        validatePassword(password),
        validateEmail(email),
        validateNickname(nickname)
    ]);

    for (const validation of validations) {
        if (!validation.valid) {
            return { valid: false, message: validation.message };
        }
    }
    return { valid: true };
};

const checkExistence = async ({ username, email, nickname }) => {
    const [usernameExists, emailExists, nicknameExists] = await Promise.all([
        checkUsernameExists(username),
        checkEmailExists(email),
        checkNicknameExists(nickname)
    ]);

    if (usernameExists.exists) return { exists: true, message: usernameExists.message };
    if (emailExists.exists) return { exists: true, message: emailExists.message };
    if (nicknameExists.exists) return { exists: true, message: nicknameExists.message };

    return { exists: false };
};

router.post('/register', [
    body('username').trim().escape(),
    body('nickname').trim().escape(),
    body('password').trim().escape(),
    body('email').isEmail().normalizeEmail()
], RateLimiter(5, 300), async (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        return res.status(400).json({ status: "error", message: errors.array() });
    }

    const { username, nickname, password, email } = req.body;

    const inputValidation = await validateInputs({ username, nickname, password, email });
    if (!inputValidation.valid) {
        return res.status(400).json({ status: "error", message: inputValidation.message });
    }

    try {
        const existenceCheck = await checkExistence({ username, email, nickname });
        if (existenceCheck.exists) {
            return res.status(400).json({ status: "error", message: existenceCheck.message });
        }

        const activationToken = crypto.randomBytes(64).toString('hex');
        const hashedPassword = await argon2.hash(password);

        const newUser = new User({
            username: username.toLowerCase(),
            nickname,
            password: hashedPassword,
            email: email.toLowerCase(),
            activation_token: activationToken
        });

        await newUser.save();

        const activationLink = `${process.env.BASE_URL}${process.env.BASE_PATH}/activate/${activationToken}`;
        await mail(email, 'Activate your account', `Click here to activate your account: ${activationLink}`);

        logActivity(newUser._id, 'registration', 'User registered', req.ip);
        notifyAdmins(`New user registered: ${username}, email: ${email}, nickname: ${nickname}, IP: ${req.ip}`);

        res.json({ status: "success", message: "User registered successfully" });
    } catch (error) {
        console.error(error);
        res.status(500).json({ status: "error", message: "Internal server error" });
    }
});

router.get('/activate/:token', RateLimiter(10, 900), async (req, res) => {
    try {
        const user = await User.findOne({ activation_token: req.params.token });
        if (!user) return res.status(404).json({ status: "error", message: "Activation token not found" });

        user.activation_token = null;
        await user.save();

        // Create a license for the user
        const licenseKey = crypto.randomBytes(16).toString('hex');
        const newLicense = new Licenses({
            key: licenseKey,
            activated_by: user._id,
            activated_at: new Date(),
            features: ['fov', 'zoom'],
            runtime: '10y',
            expires_at: new Date(Date.now() + convertDurationToMilliseconds('unlimited')) // Adjust runtime as needed
        });

        await newLicense.save();

        logActivity(user._id, 'account_activation', 'Account activated and license created', req.ip);
        res.json({ status: "success", message: "Account activated successfully and license created" });
    } catch (error) {
        console.error(error);
        res.status(500).json({ status: "error", message: "Internal server error" });
    }
});

module.exports = router;