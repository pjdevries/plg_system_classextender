# Obix Class Extender System Plugin

This system plugin allows the adaptation and extension of 
Joomla! or 3rd party core classes.

## Rationale

When building websites, many times there is the need for 
adaptation or extension of core functionality. For instance, 
when a Joomla! list model does not support filtering on a 
specific user id. An example:
- You are building a website where registered users can manage 
  their own articles, keeping in mind that, since the 
  introduction of custom fields, articles can be almost 
  anything you can imagine.
- In the frontend you provide a fancy article manager.
- Obviously you don't want users to modify eachother's 
  articles, so you configure the ACL properly. 
- You also don't want your users to see eachother's articles
  in the article manager. That presents you with a problem: 
  the **Category List** menu item type does not allow you to 
  filter on an article's `created_by` field.  
- The conventional approach is to create a template override, 
  in which you run the article list query for a second time, 
  but this time filtering on the id of the logged in user.

With this plugin you can prevent that second, redundant query. 
Joomla! core's `ContentModelArticles` already has the logic
in place to filter on com_content's `created_by`. So the only 
thing we have to do, is create an override for the `getItems()` 
method of the `ContentModelCategory` class, to which we add 
filtering on the id of the logged in user.     

## Installation and configuration

Installation of the plugin is the same as for any other Joomla!
extension. If it is installed for the first time, as opposed
to ugraded, it should be activated automatically. Doesn't
do any harm to check though :)

After the installation, it is important to check the order of 
all system plugins. This plugin relies on being the first to
load the classes that are being extended. It must therefore
be executed before the original classes are loaded by other
parts of the Joomla! system. Making this plugin the first in
the execution order, ensures that happens. 

### Configuration

**Class extender folder path**

A path, relative to the website root, of the folder where 
the plugin expects to find the configuration file and 
extended classes. Leading and trailing slashes are ignored.

**Create if non existent**

Whether or not to create the extender folder if it 
doesn't exist yet. 

## What's next

### Create an extended class file

* Create a folder for your extended class like so:
  * Assuming the base path of the extender folder is: `[web root]/<path_to_extender_folder>`.
  * Assuming the path of the folder that contains 
    _the original class_ you want to extend is: `[web root]/<path_to_original_class_folder>`.
  * Create the following folder: `[web root]/<path_to_extender_folder>/<path_to_original_class_folder>`.
* Create a file for your extended class like so:
  * Assuming the name of the class you are extending is: 
    `SomeClass`.
  * Create the following file: `[web root]/<path_to_extender_folder>/<path_to_original_class_folder>/SomeClass.php`
* The code in the extended class file must contain a class 
  definition 
  * with a name identical to the name of the class you are 
    extending and
  * extending a class with that same class name but with 
    `ExtensionBase` appended to it,
  * like so: `class SomeClass extends SomeClassExtensionBase`.
* Add the JSON encoded specifics of the extended class to 
  file `[web root]/<path_to_extender_folder>/class_extensions.json` 
  (see [JSON specifications](#json-spec) below).

### Example

Class extensions are expected in a folder named 
`class_extensions`. By default that folder is a subfolder of 
the default template, but its location can be changed in the plugin settings.

For the example we will assume that protostar is the default 
template and the `class_extensions` folder is in the default location.

To create an override of the core Joomla! content category 
model, do the following:
* Check if folder `[web root]/templates/protostar/class_extensions/components/com_content/models` 
  exists and create it if it doesn't.
* In the `.../models` folder, create a file for the extended 
  class, named `ContentModelCategory.php`.
* In the extended class file create the following class 
  definition:

  ```
  class ContentModelCategory extends ContentModelCategoryExtensionBase
  {
      ...
      ...
  }
  ```
* Assuming the file does not yet exist, create file 
  `[web root]/templates/protostar/class_extensions/class_extensions.json`.
* Add the following to the the JSON file:
  ```
  [
    {
      "class": "ContentModelCategory",
      "file": "components/com_content/models/category.php"
    }
  ]
  ```

## How it works

* The `onAfterInitialise` handler of the system plugin 
  processes the specifications in the JSON file that _don't_ 
  have  specific routes. The `onAfterRoute` handler processes 
  the specifications that _do_ have specifc routes.
* For each extended class file found, a copy of the original 
  class file is created. The path of that file is composed as 
  follows:
    * The directory for the copied file is the base path of the 
      original file, relative to the website root, and 
      prefixed with `[web root]/templates/protostar/class_extensions`
    * If a route specification exists, the name of that route is appended 
      to the new directory (see [JSON specifications](#json-spec) below). 
    * The filename of the copy is that of the original class 
      file, but with `ExtensionBase` appended to it. 
    * So for the example above, this will result in the file 
      `[web root]/templates/protostar/class_extensions/components/com_content/models/ContentModelCategoryExtensionBase`.
* If a copy already exists and the original class file is 
  newer than the existing copy, the old copy is overwritten 
  with the newer version.
* The name of the class in the copied file gets `ExtensionBase` appended to it. So for the example above, 
  this will result in `class ContentModelCategoryExtensionBase`.
* Using `include_once`, the copied class, with the new name, 
  is loaded first, followed by the extended class, having the
  same name as the original class.
* Because the system plugin is the first to load the class, 
  later references to the same class will use the already 
  loaded, extended class definition.
  
## What doesn't work

Due to the way Joomla! handles legacy, non namespaced classes, 
a whole bunch of core classes can not be extended using this
plugin. Those classes can be found in `[web root]/libraries/classmap.php`. This file is included during 
the bootstrap phase, before any plugin events are triggered.

## <a id="json-spec">JSON specification</a>

File `[web root]/<path_to_extender_folder>/class_extensions.json` contains JSON encoded information 
about the (core) classes to be extended. It contains an array of objects. Each 
object describes a single class to be extended. At the moment 
of this writing, an object description contains the following 
attributes:
   ```
   {
     "class": ...,
     "file": ...,
     "route": {
        "name": ...,
        "option": ...,
        "view": ...,
        "layout": ...
     }
   }
   ```
`class`: the name of the class to be extended.

`file`: the path of the file containing the class to be 
extended, relative to the website root.

`route` an optional set of attributes, describing the route to 
match for the extended class for be effectuated. If not 
present, the extended class is always in effect.

`route.name`: the name of the subdirectory to be added to the 
default path, when looking for an extended class definition.

`route.option`, `route.view` and `route.layout`: the values to 
compare to the request parameters with the same names, 
when determining if a route matches.  

## Credits

The idea for this plugin came from an earlier prototype by 
[Herman Peeren](https://hermanpeeren.nl/) \
[Herman's GitHub](https://github.com/HermanPeeren).
