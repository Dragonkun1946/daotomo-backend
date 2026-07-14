const passport = require('passport');
const DiscordStrategy = require('passport-discord').Strategy;
const GoogleStrategy = require('passport-google-oauth20').Strategy;
const User = require('../models/User');

// We use passport only to run the OAuth handshake — no sessions, no serialize/deserialize.
// The route handler issues our own JWT after this runs (see routes/auth.js).

if (process.env.DISCORD_CLIENT_ID && process.env.DISCORD_CLIENT_SECRET) {
  passport.use(new DiscordStrategy(
    {
      clientID: process.env.DISCORD_CLIENT_ID,
      clientSecret: process.env.DISCORD_CLIENT_SECRET,
      callbackURL: process.env.DISCORD_CALLBACK_URL || '/api/auth/discord/callback',
      scope: ['identify', 'email'],
    },
    async (accessToken, refreshToken, profile, done) => {
      try {
        let user = await User.findOne({ discordId: profile.id });
        if (!user) {
          // Link to an existing account with the same email, otherwise create a new one
          const email = profile.email || `${profile.id}@discord.daotomo.site`;
          user = await User.findOne({ email });
          if (user) {
            user.discordId = profile.id;
          } else {
            user = new User({
              username: `${profile.username}#${profile.discriminator}`.slice(0, 30),
              email,
              discordId: profile.id,
              avatar: profile.avatar
                ? `https://cdn.discordapp.com/avatars/${profile.id}/${profile.avatar}.png`
                : '',
            });
          }
          await user.save({ validateBeforeSave: false });
        }
        done(null, user);
      } catch (err) {
        done(err, null);
      }
    }
  ));
}

if (process.env.GOOGLE_CLIENT_ID && process.env.GOOGLE_CLIENT_SECRET) {
  passport.use(new GoogleStrategy(
    {
      clientID: process.env.GOOGLE_CLIENT_ID,
      clientSecret: process.env.GOOGLE_CLIENT_SECRET,
      callbackURL: process.env.GOOGLE_CALLBACK_URL || '/api/auth/google/callback',
      scope: ['profile', 'email'],
    },
    async (accessToken, refreshToken, profile, done) => {
      try {
        let user = await User.findOne({ googleId: profile.id });
        if (!user) {
          const email = profile.emails?.[0]?.value || `${profile.id}@google.daotomo.site`;
          user = await User.findOne({ email });
          if (user) {
            user.googleId = profile.id;
          } else {
            user = new User({
              username: (profile.displayName || `player${profile.id.slice(-6)}`).slice(0, 30),
              email,
              googleId: profile.id,
              avatar: profile.photos?.[0]?.value || '',
            });
          }
          await user.save({ validateBeforeSave: false });
        }
        done(null, user);
      } catch (err) {
        done(err, null);
      }
    }
  ));
}

module.exports = passport;
