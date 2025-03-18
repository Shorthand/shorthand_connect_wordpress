/**
 * Stories structure definition.
 *
 * @package Shorthand Connect
 */

let options = {
  valueNames: [
    'version_value',
    'title',
    {name: 'description', attr: 'value'},
    'updated_value',
    {name: 'updated_timestamp', attr: 'data-timestamp'},
    'published_value',
    {name: 'published_timestamp', attr: 'data-timestamp'},
    {name: 'story_id', attr: 'value'},
    {name: 'image', attr: 'src'},
    {name: 'imagealt', attr: 'alt'},
  ],
  item: '<li class="story"><label><input class="story_id" name="story_id" onclick="selectStoryRadio(this)" type="radio" value=""/><input class="description" name="description" type="hidden" value=""/><img class="image imagealt" width="190" src="" alt=""/><span class="version">Story version: <span class="version_value"></span></span><span class="title"></span><div class="updated_container">Updated: <span class="updated_timestamp updated_value"></span> ago</div><div class="published_container">Last published: <span class="published_timestamp published_value"></span> ago</div></label></li>'
};

let storiesList = new List('stories-list', options);

