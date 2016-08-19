# WooCommerce-Salesforce-integration
Salesforce and Woocommerce integration for WordPress. (A syncing engine for all sort of datasets and configurations)

[![Intro](http://img.youtube.com/vi/W67pldjT_pE/0.jpg)](https://www.youtube.com/watch?v=W67pldjT_pE "Intro")

# Installation procedure
1. Upload the ‘woocommerce-salesforce-integration’ directory to your ‘/wp-content/plugins/’ directory using FTP, SFTP or similar method.

2. Activate Neuralab WooCommerce Salesforce Integration plugin from your Wordpress Plugin page.


# Setup procedure

1. After the plugin activation go to WooCommerce → Settings → Integration tab. There you’ll see two inputs that you need to fill in, Consumer Key and Consumer Secret, and Callback URL which you’ll copy and paste to Salesforce.

2. To obtain Consumer Key and Secret, login to your Salesforce account, go to Setup (top right corner) and on the left menu, Build section, choose Create and click on the Apps. You need to create a new Connected App so click on the New button (Connected Apps section). 

3. Fill all required data and enable OAuth Settings. Give this new app next permissions:
 - Access and manage your data (api)
 - Perform requests on your behalf at any time (refresh_token, offline_access)

4. When done, click Save and you’ll be redirect to new page similar to one below. Note that you’ll need to wait 10 to 15 minutes for Salesforce to update changes you made.

5. Copy and paste Consumer Key and Secret to plugin Settings page and click on the Save Changes. 

6. You’ll be asked to allow the plugin usage of your Salesforce data and after that redirected back to plugins Settings page where you’ll see rest of the interface with default relationships between WooCommerce and Salesforce objects.

7. Edit and activate default relationships and start using the plugin.
