const gulp = require("gulp");
const needle = require("needle");
const fs = require("fs");
const cryptojs = require("crypto-js");
const fs_config = require("./fs-config.json");

// Graceful fallback — node-notifier is dev-only, not required in CI
let notifier;
try {
  notifier = require("node-notifier");
} catch (e) {
  notifier = { notify: () => {} };
}

function base64_url_encode(str) {
  str = Buffer.from(str).toString("base64");
  str = str.replace(/=/g, "");
  return str;
}

gulp.task("freemius-deploy", function (done) {
  const args = {
    developer_id: fs_config.developer_id,
    plugin_id: fs_config.plugin_id,
    secret_key: fs_config.secret_key,
    public_key: fs_config.public_key,
    zip_name: "plugin.zip",
    zip_path: "./",
    add_contributor: false,
  };

  if (!Number.isInteger(args.plugin_id)) {
    return done("Invalid plugin ID.");
  }

  const resource_url = `/v1/developers/${args.developer_id}/plugins/${args.plugin_id}/tags.json`;
  const boundary = "----" + new Date().getTime().toString(16);
  const content_md5 = "";
  const date = new Date().toUTCString();
  const string_to_sign = [
    "POST",
    content_md5,
    `multipart/form-data; boundary=${boundary}`,
    date,
    resource_url,
  ].join("\n");

  const hash = cryptojs.HmacSHA256(string_to_sign, args.secret_key);
  const auth = `FS ${args.developer_id}:${args.public_key}:${base64_url_encode(hash.toString())}`;
  const buffer = fs.readFileSync(`${args.zip_path}/${args.zip_name}`);

  const data = {
    add_contributor: args.add_contributor ? "true" : "false",
    file: {
      buffer: buffer,
      filename: args.zip_name,
      content_type: "application/zip",
    },
  };

  const options = {
    multipart: true,
    boundary: boundary,
    headers: {
      "Content-MD5": content_md5,
      Date: date,
      Authorization: auth,
    },
  };

  needle.post(
    `https://api.freemius.com${resource_url}`,
    data,
    options,
    function (error, response, body) {
      let message;
      if (error) {
        message = "Error deploying to Freemius.";
        notifier.notify({ message: message });
        console.log("\x1b[31m%s\x1b[0m", message);
        return done(message);
      }
      if (typeof body === "object") {
        if (typeof body.error !== "undefined") {
          message = "Error: " + body.error.message;
          notifier.notify({ message: message });
          console.log("\x1b[31m%s\x1b[0m", message);
          return done(message);
        }
        message = `Successfully deployed v${body.version} to Freemius. Go and release it: https://dashboard.freemius.com/#!/live/plugins/${args.plugin_id}/deployment/`;
        notifier.notify({ message: message });
        console.log("\x1b[32m%s\x1b[0m", message);
        return done();
      }
      message = "Try deploying to Freemius again in a minute.";
      notifier.notify({ message: message });
      console.log("\x1b[33m%s\x1b[0m", message);
      done();
    }
  );
});