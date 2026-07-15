const passport = require('passport');
const DiscordStrategy = require('passport-discord').Strategy;
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

module.exports = passport;
