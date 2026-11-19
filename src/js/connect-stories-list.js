/**
 * Stories structure definition.
 *
 * @package Shorthand Connect
 */

const storyOptions = {
  valueNames: [
    'title',
    {name: 'description', attr: 'value'},
    {name: 'updated_at', attr: 'value'},
    'updated_value',
    {name: 'updated_timestamp', attr: 'data-timestamp'},
    'published_label',
    {name: 'published_timestamp', attr: 'data-timestamp'},
    {name: 'story_id', attr: 'value'},
    {name: 'image', attr: 'src'},
    {name: 'imagealt', attr: 'alt'},
    {name: 'wordpress_published', attr: 'data-published'},
    'wordpress_status_label',
    'wordpress_action_label',
    {name: 'wordpress_url', attr: 'href'},
  ],
  item: '<li class="story shorthand-card-item"><div class="shorthand-card-shell"><label class="shorthand-card"><input class="story_id" name="story_id" type="radio" value=""/><input class="description" name="description" type="hidden" value=""/><input class="updated_at" type="hidden" value=""/><img class="image imagealt" width="190" src="" alt=""/><span class="title"></span><dl class="shorthand-card-metadata"><div><dt>Edited</dt><dd><span class="updated_timestamp updated_value"></span> ago</dd></div><div><dt>Published</dt><dd><span class="published_timestamp published_label"></span></dd></div></dl></label><div class="wordpress-status wordpress_published" data-published="false"><span class="wordpress_status_label"></span><a class="wordpress_action_label wordpress_url" href=""></a></div></div></li>'
};

const projectOptions = {
  valueNames: [
    'title',
    'story_count',
    'updated_value',
    {name: 'updated_timestamp', attr: 'data-timestamp'},
    {name: 'project_id', attr: 'data-project-id'},
    {name: 'story_count_value', attr: 'data-story-count'},
    {name: 'image', attr: 'src'},
    {name: 'imagealt', attr: 'alt'},
    {name: 'wordpress_published', attr: 'data-published'},
    'wordpress_status_label',
    'wordpress_action_label',
    {name: 'wordpress_url', attr: 'href'},
  ],
  item: '<li class="project shorthand-card-item"><div class="shorthand-card-shell"><button class="shorthand-card project-card project_id story_count_value" type="button" data-project-id="" data-story-count="" aria-pressed="false"><img class="image imagealt" width="190" src="" alt=""/><span class="title"></span><dl class="shorthand-card-metadata"><div><dt>Stories</dt><dd><span class="story_count"></span></dd></div><div><dt>Edited</dt><dd><span class="updated_timestamp updated_value"></span> ago</dd></div></dl></button><div class="wordpress-status wordpress_published" data-published="false"><span class="wordpress_status_label"></span><a class="wordpress_action_label wordpress_url" href=""></a></div></div></li>'
};

const storiesList = new List('stories-list', storyOptions);
const projectsList = document.getElementById('projects-list') ? new List('projects-list', projectOptions) : null;
