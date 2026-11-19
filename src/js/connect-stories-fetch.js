const limit = 100;
const storiesContainer = document.querySelector('#stories-list ul.list');
const selectionContainer = document.querySelector('#shorthand_wpt_shorthand_story .inside');
let cursor;
let isFetching = false;

const loadingBar = document.createElement('div');
loadingBar.className = 'loading-bar';
loadingBar.innerHTML = "<span class='loader'></span> Fetching Shorthand content...";
selectionContainer.append(loadingBar); // phpcs:ignore WordPressVIPMinimum.JS.HTMLExecutingFunctions.append

function showLoadingBar() {
  loadingBar.classList.add('show');
}

function hideLoadingBar() {
  loadingBar.classList.remove('show');
}

function showPickerError(containerId, message) {
  const container = document.getElementById(containerId);
  const emptyMessage = document.createElement('div');
  emptyMessage.className = 'errorMsg';
  emptyMessage.textContent = message;
  container.append(emptyMessage); // phpcs:ignore WordPressVIPMinimum.JS.HTMLExecutingFunctions.append
}

async function requestJson(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': wp_server.nonce,
    },
    ...options,
  });
  const json = await response.json();
  if (!response.ok) {
    throw new Error(json.message || `HTTP ${response.status}`);
  }
  return json;
}

async function fetchMoreStories() {
  if (isFetching) return;
  if (storiesList.items.length > 0 && storiesList.items.length % limit !== 0) return;

  isFetching = true;
  showLoadingBar();
  const lastStory = storiesList.items[storiesList.items.length - 1];
  if (lastStory) cursor = lastStory.values().updated_at;
  const separator = wp_server.stories_url.includes('?') ? '&' : '?';
  const url = cursor
    ? `${wp_server.stories_url}${separator}cursor=${encodeURIComponent(cursor)}&limit=${limit}`
    : `${wp_server.stories_url}${separator}limit=${limit}`;

  try {
    const stories = await requestJson(url);
    storiesList.add(stories || []);
    if (!storiesList.items.length) {
      showPickerError('stories-list', 'No standalone Stories are currently ready for publishing in Shorthand.');
    }
  } catch (error) {
    showPickerError('stories-list', error.message || 'Stories could not be loaded from Shorthand.');
  } finally {
    isFetching = false;
    hideLoadingBar();
  }
}

async function fetchProjects() {
  if (!projectsList) return;
  showLoadingBar();
  try {
    const projects = await requestJson(wp_server.projects_url);
    projectsList.add(projects || []);
    if (!projectsList.items.length) {
      showPickerError('projects-list', 'No Projects are currently ready for publishing in Shorthand.');
    }
  } catch (error) {
    showPickerError('projects-list', error.message || 'Projects could not be loaded from Shorthand.');
  } finally {
    hideLoadingBar();
  }
}

function activateTab(tabName) {
  document.querySelectorAll('[data-shorthand-tab]').forEach(function (tab) {
    const isActive = tab.dataset.shorthandTab === tabName;
    tab.classList.toggle('nav-tab-active', isActive);
    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    tab.setAttribute('tabindex', isActive ? '0' : '-1');
    document.getElementById(tab.getAttribute('aria-controls')).hidden = !isActive;
  });
}

const tabs = Array.from(document.querySelectorAll('[data-shorthand-tab]'));
tabs.forEach(function (tab, index) {
  tab.addEventListener('click', function () {
    activateTab(tab.dataset.shorthandTab);
  });
  tab.addEventListener('keydown', function (event) {
    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
    event.preventDefault();
    const offset = event.key === 'ArrowRight' ? 1 : -1;
    const nextTab = tabs[(index + offset + tabs.length) % tabs.length];
    activateTab(nextTab.dataset.shorthandTab);
    nextTab.focus();
  });
});

if (projectsList) {
  document.getElementById('projects-list').addEventListener('click', function (event) {
    const button = event.target.closest('.project-card');
    if (!button) return;

    document.querySelectorAll('.project-card').forEach(function (card) {
      const isSelected = card === button;
      card.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
      card.closest('li.project').classList.toggle('selected', isSelected);
    });
    document.querySelectorAll('li.story.selected').forEach(function (story) {
      story.classList.remove('selected');
      const input = story.querySelector('.story_id');
      if (input) input.checked = false;
    });

    document.getElementById('shorthand-project-id').value = button.dataset.projectId;
    document.getElementById('title').value = button.querySelector('.title').textContent.trim();
    document.getElementById('title-prompt-text').classList.add('screen-reader-text');
    const abstract = document.getElementById('abstract');
    if (abstract) abstract.value = '';
  });

  let projectImportStarting = false;

  async function submitSelectedProject(event, targetStatus) {
    const projectId = document.getElementById('shorthand-project-id').value;
    if (!projectId) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    if (projectImportStarting) return;
    projectImportStarting = true;

    const actionButtons = [document.getElementById('save-post'), document.getElementById('publish')].filter(Boolean);
    actionButtons.forEach(function (actionButton) {
      actionButton.disabled = true;
    });
    showLoadingBar();
    loadingBar.lastChild.textContent = ` ${wp_server.project_saving}`;

    const errorNotice = document.querySelector('.project-import-error');
    errorNotice.hidden = true;
    try {
      const result = await requestJson(wp_server.project_import_url, {
        method: 'POST',
        body: JSON.stringify({
          project_id: projectId,
          post_status: targetStatus,
          placeholder_post_id: Number(wp_server.placeholder_post_id),
        }),
      });
      const returnUrl = new URL(wp_server.project_list_url);
      returnUrl.searchParams.set('shorthand_project_id', result.project_id);
      window.location.assign(returnUrl.toString());
    } catch (error) {
      errorNotice.querySelector('p').textContent = error.message || wp_server.project_error;
      errorNotice.hidden = false;
      projectImportStarting = false;
      actionButtons.forEach(function (actionButton) {
        actionButton.disabled = false;
      });
      hideLoadingBar();
    }
  }

  const saveDraftButton = document.getElementById('save-post');
  const publishButton = document.getElementById('publish');
  if (saveDraftButton) {
    saveDraftButton.addEventListener('click', function (event) {
      submitSelectedProject(event, 'draft');
    }, true);
  }
  if (publishButton) {
    publishButton.addEventListener('click', function (event) {
      submitSelectedProject(event, 'publish');
    }, true);
  }
}

const storyObserver = new IntersectionObserver(
  function (entries) {
    if (entries[0].isIntersecting) {
      storyObserver.disconnect();
      fetchMoreStories();
    }
  },
  {rootMargin: '100px'}
);

const mutationObserver = new MutationObserver(function () {
  const lastStory = document.querySelector('.story:last-child');
  if (lastStory) {
    storyObserver.disconnect();
    storyObserver.observe(lastStory);
  }
});

activateTab(wp_server.active_tab || 'stories');
fetchMoreStories();
fetchProjects();
mutationObserver.observe(storiesContainer, {childList: true});
