module.exports = {
    tagFormat: "${version}",
    branch: "master",
    plugins: [
      ["@semantic-release/npm", { npmPublish: false }],
      "@semantic-release/github",
      [
        "semantic-release-plugin-update-version-in-files",
        {
          "files": [
            "package.json",
            "wp-job-manager-bullhorn.php"
          ],
          "placeholder": "0.0.0-development"
        }
      ]
    ]
  };
