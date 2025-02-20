const limit = 100;
const container = document.querySelector('#stories-list ul.list');
const selectionContainer = document.querySelector("#shorthand_wpt_shorthand_story .inside");
let cursor;
let isFetching = false; 

//Add loading spinner
const loadingBar = document.createElement("div");
loadingBar.className = "loading-bar";
loadingBar.innerHTML = "<span class='loader'></span> Fetching more stories...";
selectionContainer.append(loadingBar); // phpcs:ignore WordPressVIPMinimum.JS.HTMLExecutingFunctions.append

const showLoadingBar = () =>{
    loadingBar.classList.add("show");
}
const hideLoadingBar = () =>{
    loadingBar.classList.remove("show");
}

const fetchMoreStories = async () => {
    if (isFetching) return;
    //check if last fetch wasn't the full limit - if so we're at the end
    if ((storiesList.items.length%limit)/limit > 0) return;
    isFetching = true;
    showLoadingBar();
    const lastStory = storiesList.items[storiesList.items.length - 1];
    if (lastStory) {
        cursor = lastStory.values().updated_at
    }
    const additionQuery = wp_server.url.indexOf('?') > 0 ? "&" : "?";
    const url = cursor ? `${wp_server.url}${additionQuery}cursor=${cursor}&limit=${limit}` : `${wp_server.url}${additionQuery}limit=${limit}`;
    const response = await fetch(url, {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
            'X-WP-Nonce': wp_server.nonce,
        },
        credentials: "include"
    });

    const json = await response.json();
    storiesList.add(json);
    isFetching = false;
    hideLoadingBar();
}

//Select existing story

// Fetch more on scroll
// Intersection Observer to detect when the last item is visible
const observer = new IntersectionObserver((entries) => {
    const lastStory = entries[0];
    if (lastStory.isIntersecting) {
        observer.disconnect();
        fetchMoreStories();
    }
}, { rootMargin: "100px" });

// Observe new last items as they are added
const mutationObserver = new MutationObserver(() => {
    const lastStory = document.querySelector(".story:last-child");
    if (lastStory) {
        observer.disconnect();
        observer.observe(lastStory);
    }
});

// Initial Data Load
fetchMoreStories().then(() => {
    // Observe the last item in the list
    const lastStory = document.querySelector(".story:last-child");
    if (lastStory) {
        observer.disconnect();
        observer.observe(lastStory);
    }
});

mutationObserver.observe(container, { childList: true });