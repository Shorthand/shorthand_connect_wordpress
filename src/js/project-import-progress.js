/**
 * Displays asynchronous Shorthand Project import progress.
 *
 * @package Shorthand Connect
 */

(function () {
  const root = document.getElementById('shorthand-project-progress');
  if (!root || typeof shorthand_project_progress === 'undefined') return;

  function appendTextElement(parent, tagName, className, text) {
    const element = document.createElement(tagName);
    element.className = className;
    element.textContent = text;
    parent.append(element); // phpcs:ignore WordPressVIPMinimum.JS.HTMLExecutingFunctions.append
    return element;
  }

  function format(template, values) {
    return values.reduce(function (output, value, index) {
      return output.replace(`%${index + 1}$d`, value).replace(`%${index + 1}$s`, value);
    }, template).replace(/%%/g, '%');
  }

  function renderJobs(jobs) {
    root.replaceChildren();
    const selectedProjectId = shorthand_project_progress.project_id;
    const visibleJobs = jobs.filter(function (job) {
      return selectedProjectId ? job.project_id === selectedProjectId : job.status === 'running';
    });

    if (!visibleJobs.length) {
      appendTextElement(root, 'p', '', shorthand_project_progress.complete);
      root.setAttribute('aria-busy', 'false');
      return false;
    }

    let hasRunningJob = false;
    let hasCompletedSelectedJob = false;
    visibleJobs.forEach(function (job) {
      const item = document.createElement('div');
      item.className = 'shorthand-project-progress-item';
      const actionLabel = job.target_status === 'publish'
        ? shorthand_project_progress.publishing
        : shorthand_project_progress.saving_drafts;
      appendTextElement(item, 'p', 'shorthand-project-progress-label', `${job.project_title}: ${actionLabel}`);

      const progress = document.createElement('progress');
      progress.max = 100;
      progress.value = job.percent;
      progress.setAttribute('aria-label', format(shorthand_project_progress.aria_progress, [job.project_title, job.percent]));
      item.append(progress); // phpcs:ignore WordPressVIPMinimum.JS.HTMLExecutingFunctions.append

      let statusText = format(shorthand_project_progress.progress_format, [job.percent, job.position, job.total]);
      if (job.status === 'complete') {
        statusText = `100% — ${shorthand_project_progress.complete}`;
        hasCompletedSelectedJob = Boolean(selectedProjectId);
      } else if (job.status === 'failed') {
        statusText = `${shorthand_project_progress.failed}: ${job.error}`;
      } else {
        hasRunningJob = true;
      }
      appendTextElement(item, 'p', 'description shorthand-project-progress-status', statusText);
      root.append(item); // phpcs:ignore WordPressVIPMinimum.JS.HTMLExecutingFunctions.append
    });

    root.setAttribute('aria-busy', hasRunningJob ? 'true' : 'false');
    if (hasCompletedSelectedJob) {
      window.setTimeout(function () {
        window.location.assign(shorthand_project_progress.list_url);
      }, 1200);
    }
    return hasRunningJob;
  }

  async function pollProgress() {
    try {
      const response = await fetch(shorthand_project_progress.status_url, {
        credentials: 'include',
        headers: {'X-WP-Nonce': shorthand_project_progress.nonce},
      });
      const jobs = await response.json();
      if (!response.ok) throw new Error(jobs.message || `HTTP ${response.status}`);
      if (renderJobs(Array.isArray(jobs) ? jobs : [])) {
        window.setTimeout(pollProgress, 2500);
      }
    } catch (error) {
      root.replaceChildren();
      appendTextElement(root, 'p', 'notice-error', shorthand_project_progress.progress_error);
      root.setAttribute('aria-busy', 'false');
    }
  }

  pollProgress();
}());
