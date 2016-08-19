# WooCommerce-Salesforce-integration
Salesforce and Woocommerce integration for WordPress. (A syncing engine for all sort of datasets and configurations)

[![Introduction Video](http://img.youtube.com/vi/W67pldjT_pE/0.jpg)](https://www.youtube.com/watch?v=W67pldjT_pE "Introduction Video")

# Installation procedure
1. Upload the ‘woocommerce-salesforce-integration’ directory to your ‘/wp-content/plugins/’ directory using FTP, SFTP or similar method.

2. Activate Neuralab WooCommerce Salesforce Integration plugin from your Wordpress Plugin page.


# Setup procedure

1. After the plugin activation go to WooCommerce → Settings → Integration tab. There you’ll see two inputs that you need to fill in, Consumer Key and Consumer Secret, and Callback URL which you’ll copy and paste to Salesforce.

2. To obtain Consumer Key and Secret, login to your Salesforce account, go to Setup (top right corner) and on the left menu, Build section, choose Create and click on the Apps. You need to create a new Connected App so click on the New button (Connected Apps section). 

3. Fill all required data and enable OAuth Settings. Give this new app next permissions:
 - Access and manage your data (api)
 - Perform requests on your behalf at any time (refresh_token, offline_access)
 
 [![Setup](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Setup-1.png)](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Setup-1.png "Setup")


4. When done, click Save and you’ll be redirect to new page similar to one below. Note that you’ll need to wait 10 to 15 minutes for Salesforce to update changes you made.

 [![Setup](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Setup-2.png)](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Setup-2.png "Setup")

5. Copy and paste Consumer Key and Secret to plugin Settings page and click on the Save Changes. 

6. You’ll be asked to allow the plugin usage of your Salesforce data and after that redirected back to plugins Settings page where you’ll see rest of the interface with default relationships between WooCommerce and Salesforce objects.

 [![Setup](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Setup-3.png)](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Setup-3.png "Setup")

7. Edit and activate default relationships and start using the plugin.

# Standard operations

## New relationship between Salesforce and WooCommerce objects

1. At the main Settings page (WooCommerce → Settings → Integration), under section New Relationship, choose Salesforce and WooCommerce objects that you want to connect and click on Add button.

 [![Setup](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-1.png)](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-1.png "Setup")
 
2. You’ll be redirect to a new form where you can see the list of Salesforce object fields on the left. For each field you can see type of field like string, boolean, picklist, if it’s required (red asterisk - *) or not, and belonging select of WooCommerce object fields. Make sure that the types of fields match! You can also add custom static values for each field. For example, for the fields type string or textarea it’s a custom string, for integer and double it’s a number, for boolean fields it’s a true or false values, etc. For a field type picklist you can only choose predefined Salesforce values. 
 
 [![Setup](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-2.png)](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-2.png "Setup")

3. You’ll also need to define unique fields for new relationships. Unique fields are chosen Salesforce fields that have a role of unique identifier. For example, if we have a Salesforce’s Product object, we can mark Product name and Product code as unique fields because the plugin, before synchronization, checks if the object already exists in the Salesforce by values of unique fields, in this case Product name and Product code. So if Product was synchronized before, plugin will just obtain it’s Salesforce ID and leave the object as is. 

4. Required Salesforce objects are objects connected to a Salesforce object that we chose for this relationship. Each object can have numerous connections that are required for his creation (you can find object definitions in your Salesforce account). For synchronization to be successful you’ll need to add required objects, mark them as active and create relationships from them.

 [![Setup](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-3.png)](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-3.png "Setup")
 
5. When done with defining relationship, click on Save changes button.

## Relationships management

 [![Setup](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-4.png)](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-4.png "Setup")
 
 On the main Settings page you can see table of already defined relationships. Plugin comes with default relationships between WooCommerce’s Order and Order Item objects, and default Salesforce objects like Order, Account, Product, Price Book, etc. Clicking on a relationship redirects you to a form for editing existing relationship. Already defined relationships can be deleted, activated, or deactivated. Deactivated relationships won’t be considered during the synchronization.
 
If option Automatic order sync is checked, each placed order by the customer will be automatically synchronized. Otherwise, you can synchronize orders manually by going to WooCommerce’s orders preview, click on a order you want to synchronize and on right sidebar, you’ll see a metabox with Status and Save and sync order button.

 [![Setup](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-5.png)](https://www.neuralab.net/wp-content/uploads/2016/08/NWSI-Operating-5.png "Setup")

If synchronization was successful status will change to success, otherwise it’ll change to failed with an error message that describes what went wrong. Usually it’s a missing object dependency or required field.


Happy syncing! 
 
