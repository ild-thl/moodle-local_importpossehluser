
# moodle-local_importpossehluser
# Moodle Local Import Possehl User Plugin

This plugin allows you to import users from an external database into Moodle.

## Installation

1. Download the latest version of the plugin from the Moodle plugins directory.
2. Extract the plugin files to the `local/importpossehluser` directory in your Moodle installation.
3. Log in to your Moodle site as an administrator.

## Usage

1. After installing the plugin, go to the **Site administration** > **Plugins** > **Local plugins** > **Import Possehl User**.
2. Configure the plugin settings according to your requirements.
3. There are several cron jobs that will take the following actions: 
- delete_possehluser_cron -> will delete users that are suspended and have not logged in for a time span specified in the plugin settings
- possehl_import_start_cron -> for initial user import this cron job will import a number of users specified in settings at once
- possehluser_cron -> synchronize the data from external db with the moodle user data (name, email, suspended or not)
4. To start the full import process manually in the browser interface call yoursite/local/importpossehluser/index.php and follow the steps

## Requirements

- Moodle 3.0 or later
- Possehl user data file in the specified format

## Contributing

Contributions are welcome! If you find any issues or have suggestions for improvements, please submit a pull request or open an issue on the [GitHub repository](https://github.com/your-repository).

## License

This plugin is licensed under the [GNU General Public License](https://www.gnu.org/licenses/gpl-3.0.en.html). See the [LICENSE](LICENSE) file for more details.

