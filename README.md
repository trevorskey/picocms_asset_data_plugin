Asset Data for PicoCMS
======================
This is a plugin for PicoCMS that provides data about the contents of asset folders on your web server to Twig themes.

Install
-------
Download `AssetData.php` and place it inside your `plugins` folder.

It should _just work_ with no extra effort on your part, but there are several configuration options available if you want to change how it works.

Config
------
This plugin has several configuration options that can be placed in the PicoCMS `config.yml` file.

Configuration Variable | Default Value | Description
-----------------------|---------------|------------
`assets_folder`        | "assets"      | Can be either a string or an array of strings. This is the folder(s) to be scanned and provided to Twig.
`asset_data_limit_by_location` | true  | If true, will only provide asset data related to the current page. This assumes that you have mirrored the folder structure of your `contents` folder in your asset folder(s). So, if your page is /contents/photos/album_one/index.md, your `assets` variable will only contain data from /assets/photos/album_one/ on down. If false, your `assets` variable will always contain data from /assets/ instead.
`asset_data_use_non_index_content_as_tld` | true | When enabled, Markdown files in the same folder as an index.md file will be treated the same as if they were a separate folder underneath with another index.md file inside, for the purposes of setting the top level directory (TLD). Note that this setting only does anything if `asset_data_limit_by_location` is also enabled. 
`asset_data_max_depth` | 3             | This is the maximum number of folders to go down when populating the `assets` variable. At present, the folder crawl is implemented as a recursive function, so the maximum feasible limit is just under the PHP recursion limit of 100. Setting this to `0` effectively disables this plugin. Setting to `-1` lets it scan everything. (Except of course for the recursion limit, which may be fixed in a future version.)
`asset_data_render_yaml` | false       | If enabled, this plugin will render the contents of `.yml` files into a separate `yamls` key of the `assets` array. The `.yml` file will not show up under the `files` key.
`asset_data_debug_mode_enable` | false | Will output information about folders found and dump the contents of the `asset_base` and `assets` Twig variables as HTML comments.
`asset_data_site_folder`| getcwd()     | This is the folder on your server where the site (and, presumably, the asset folders) is located. By default the plugin will determine this automatically, but the option exists in case it doesn't work properly or you have a setup where assets are stored elsewhere.

Variables Available in Twig
---------------------------
Two variables are made available in Twig after running this plugin.

Variable Name | Description
--------------|------------
`asset_base`  | Contains the URL of the folder used as the basis for the data contained in `assets`. This URL will be relative to the site base URL. For example, if your site is https://totallyawebsite.net/, this variable will contain everything after that.
`assets`      | Contains an array with potentially several keys: `folders`, `files`, and `yamls`. See "Usage Example" for examples of the structure.

Usage Example
-------------
Suppose your site's folder structure looks something like this (in addition to the other files you'd expect to exist):
~~~
/
|- assets/
|  |- photos/
|     |- album_one/
|     |  |- thumbs/
|     |  |  |- photo_one.jpg
|     |  |- photo_one.jpg
|     |- album_two/
|        |- thumbs/
|        |  |- P0133421.jpg
|        |  |- P0133422.jpg
|        |- captions.yml
|        |- P0133421.jpg
|        |- P0133422.jpg
|- content/
   |- photos/
      |- index.md
      |- album_one.md
      |- album_two.md
~~~

Then, if you left your configuration at the default values, this is what you would see in Twig when loading the content/photos/index.md page:
~~~
asset_base = "assets/photos"
assets['files'] = []
      ['folders']['album_one']['files'] = ['photo_one.jpg']
                              ['folders']['thumbs']['files'] = ['photo_one.jpg']
                                                   ['folders'] = []
                 ['album_two']['files'] = ['captions.yml', 'P0133421.jpg', 'P0133422.jpg']
                              ['folders']['thumbs']['files] = ['P0133421.jpg', 'P0133422.jpg']
                                                   ['folders'] = []
~~~
And for content/photos/album_one.md:
~~~
asset_base = "assets/photos/album_one"
assets['files'] = ['photo_one.jpg']
      ['folders']['thumbs']['files'] = ['photo_one_thumb.jpg']
                           ['folders'] = []
~~~

If you enabled `asset_data_render_yaml`, the output for contents/photos/album_two.md would be:
~~~
asset_base = "assets/photos/album_two"
assets['files'] = ['P0133421.jpg', 'P0133422.jpg']
      ['folders']['thumbs']['files'] = ['P0133421.jpg', 'P0133422.jpg']
                           ['folders'] = []
                           ['yamls'] = []
      ['yamls']['captions.yml'] = $yamlparser->parse('captions.yml')
~~~

So, here's some example Twig code for rendering either album_one.md or album_two.md:
~~~twig
<div class="container-fluid">
    {% if assets['files']|length > 0 %}
    <div class="row">
        {% for filename in assets['files'] if (filename ends with ".jpg") %}
        <a href="/{{ asset_base }}/{{ filename }}" class="col-xs-12 col-sm-6 col-md-4 col-lg-3">
            {% if assets['folders']['thumbs'] and assets['folders']['thumbs']['files'][filename] %}
                <img src="/{{ asset_base }}/thumbs/{{ filename }}" class="img-thumbnail">
            {% else %}
                <img src="/{{ asset_base }}/{{ filename }}" class="img-thumbnail">
            {% endif %}
            {% if assets['yamls']['captions.yml'][filename]['caption'] %}
            <div class="thumbnail-caption">{{ assets['yamls']['captions.yml'][filename]['caption'] }}</div>
            {% endif %}
        </a>
        {% endfor %}
    </div>
    {% else %}
    <div class="text-center">No photos found</div>
    {% endif %}
</div>
~~~

Note that if you do decide to have this plugin scan more than one asset folder, the `asset_base` variable will be an array corresponding to the given asset folders, and the `assets` variable will have one extra level with keys corresponding to the values in `asset_base`.
