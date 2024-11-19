const express = require('express');
const mongoose = require('mongoose');
const helmet = require('helmet');
const argon2 = require('argon2');
const { mail, notifyAdmins } = require('./modules/notificator');
const { logActivity, generateToken, convertDurationToMilliseconds } = require('./utils');
const { validateToken } = require('./middleware');
const { User, BannedUser, UserSettings, MemoryPointer, Settings, Licenses, Token, initializeDatabase } = require('./models');
const registerRoutes = require('./router/register');
const passwordResetRoutes = require('./router/passwordReset');
const feedbackRoutes = require('./router/feedback');
const chatRoutes = require('./router/chat');
const settingsRoutes = require('./router/settings');
const { router: statusRoutes, updateStats } = require('./router/status');
require('./bot');

const passport = require('passport');
require('./auth/discord');

const app = express();
const PORT = process.env.PORT || 3000;
const BASE_PATH = process.env.BASE_PATH || '/v2';

mongoose.connect(process.env.MONGO_URI).then(() => {
  console.log('MongoDB connected');
}).catch((error) => {
  console.error('MongoDB connection error:', error);
  process.exit(1);
});

app.use(helmet());

app.use(helmet.contentSecurityPolicy({
  directives: {
    defaultSrc: ["'self'"],
    objectSrc: ["'none'"],
    upgradeInsecureRequests: [],
  },
}));

app.use(express.json());

app.use(passport.initialize());

app.get(`${BASE_PATH}/auth/discord`, passport.authenticate('discord'));

app.get(`${BASE_PATH}/auth/discord/callback`, passport.authenticate('discord', { failureRedirect: '/', session: false }), (req, res) => {
  res.json({ status: "success", message: "Login successful", token: req.user.token });
});

app.post(`${BASE_PATH}/login`, async (req, res) => {
  const { username, password } = req.body;

  if (!username || !password) {
    return res.status(400).json({ status: "error", message: "Username and password are required" });
  }

  try {
    const user = await User.findOne({ username });
    if (!user) return res.status(401).json({ status: "error", message: "Invalid username or password" });

    const passwordMatch = await argon2.verify(user.password, password);
    if (!passwordMatch) return res.status(401).json({ status: "error", message: "Invalid username or password" });

    if (user.activation_token) return res.status(403).json({ status: "error", message: "Account not activated" });

    // Check if the user is banned
    const activeBan = await BannedUser.findOne({
      user_id: user._id,
      active: true,
      expires_at: { $gt: new Date() }
    });
    if (activeBan) {
      return res.status(403).json({ status: "error", message: `Forbidden: User is banned until ${activeBan.expires_at.toISOString()} for ${activeBan.reason}` });
    }

    const token = await generateToken(user);

    const loginNotificationText = `Hello ${user.username},\n\nYou have successfully logged in to your Sylent-X Account.\n\nDate: ${new Date().toLocaleString()}\nIP address: ${req.ip}\n\nIf this wasn't you, please change your password immediately and contact support.`;

    await mail(user.email, 'Login Notification', loginNotificationText);

    logActivity(user._id, 'login', 'User logged in', req.ip);

    notifyAdmins(`User logged in: ${user.username}, IP: ${req.ip}, Email: ${user.email}, Nickname: ${user.nickname}`, 'discord_login');

    const licenses = await Licenses.find({ activated_by: user._id });

    const memoryPointers = {};
    const validFeatures = licenses.flatMap(license => 
      license.features.filter(feature => {
        const expiresAt = new Date(license.expires_at);
        const now = new Date();
        console.log(`Feature expires at: ${expiresAt}, Current time: ${now}`);
        return expiresAt > now;
      })
    );

    for (const feature of validFeatures) {
      const pointer = await MemoryPointer.findOne({ feature });
      if (pointer) {
        memoryPointers[feature] = {
          address: pointer.address,
          offsets: pointer.offsets
        };
      } else {
        console.log(`No pointer found for feature: ${feature}`);
      }
    }

    const settings = await Settings.find();
    const settingsObject = {};
    settings.forEach(setting => {
      settingsObject[setting.name] = setting.value;
    });

    const userSettings = await UserSettings.findOne({ user_id: user._id });

    res.json({
      status: "success",
      message: "Login successful",
      token,
      user: {
        id: user._id,
        username: user.username,
        nickname: user.nickname,
        settings: userSettings ? userSettings.settings : null,
        features: validFeatures.map(feature => ({
          name: feature,
          pointer: memoryPointers[feature] || null
        }))
      },
      system: settingsObject
    });
  } catch (error) {
    console.error(error);
    res.status(500).json({ status: "error", message: "Internal server error" });
  }
});

app.post(`${BASE_PATH}/logout`, validateToken, async (req, res) => {
  try {
    await Token.deleteOne({ token: req.headers['authorization'] });
    res.json({ status: "success", message: "Logout successful" });
  } catch (error) {
    res.status(500).json({ status: "error", message: "Internal server error" });
  }
});

app.put(`${BASE_PATH}/license/activate`, validateToken, async (req, res) => {
  const { licenseKey } = req.body;

  if (!licenseKey) {
    return res.status(400).json({ status: "error", message: "Invalid license key" });
  }

  try {
    const license = await Licenses.findOne({ key: licenseKey });
    if (!license) {
      return res.status(404).json({ status: "error", message: "License not found" });
    }

    if (license.activated_by) {
      return res.status(403).json({ status: "error", message: "License already in use" });
    }

    license.activated_by = req.user._id;
    license.activated_at = new Date();
    license.expires_at = new Date(Date.now() + convertDurationToMilliseconds(license.runtime));
    await license.save();

    const user = await User.findOne({ _id: req.user._id });
    if (!user) {
      return res.status(404).json({ status: "error", message: "User not found" });
    }

    logActivity(req.user._id, 'license_activate', 'License activated', req.ip);
    res.json({ status: "success", message: "License activated successfully" });
  } catch (error) {
    res.status(500).json({ status: "error", message: "Internal server error: " + error.message });
  }
});

app.use(`${BASE_PATH}`, registerRoutes);
app.use(`${BASE_PATH}`, passwordResetRoutes);
app.use(`${BASE_PATH}/chat`, chatRoutes);
app.use(`${BASE_PATH}`, feedbackRoutes);
app.use(`${BASE_PATH}`, settingsRoutes);
app.use(`${BASE_PATH}/`, statusRoutes);

const server = app.listen(PORT, () => {
  console.log(`Server is running at http://localhost:${PORT}`);
});

initializeDatabase().then(() => {
  updateStats();
  setInterval(updateStats, 5000);
});

const gracefulShutdown = () => {
  console.log('Shutting down gracefully...');
  server.close(async () => {
    console.log('HTTP server closed.');
    await mongoose.connection.close();
    console.log('MongoDB connection closed.');
    process.exit(0);
  });
};

process.on('SIGINT', gracefulShutdown);
process.on('SIGTERM', gracefulShutdown);