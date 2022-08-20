function debounce(func) {
	var timer;
	return function (event) {
		if (timer) clearTimeout(timer);
		timer = setTimeout(func, 200, event);
	};
}
const urlParams = new URLSearchParams(window.location.search);
document.addEventListener('click', (event2) => {
	if (event2.target.classList.contains('tmy_show_all_button')) {
		Array.from(event2.target.parentElement.querySelectorAll('.d-none')).map(button => button.classList.remove('d-none'))
	}
})

function render(query) {
	const spinner = document.getElementById('tmy-search-results-spinner')
	spinner.classList.remove('d-none')
	const data = new FormData();

	data.append('action', 'frontend_searchaction');
	data.append('nonce', toshodex_search_ajax.nonce);
	data.append('query', query);
	fetch(toshodex_search_ajax.ajax_url, {
		method: "POST",
		credentials: 'same-origin',
		body: data
	}).then(res => res.json())
		.then(response => {
			console.log('response', response);
			const resultsDiv = document.createElement('div')
			if (response.posts.length === 0) {
				resultsDiv.innerHTML = "No results found"
				spinner.classList.add('d-none')
			} else {
				response.posts.map(post => {
					const link = document.createElement('a');
					const postDiv = document.createElement('div');
					postDiv.classList.add("tmy-post-div")
					const contentDiv = document.createElement('div');
					link.innerHTML = post.post_title;
					link.href = post.post_link;
					const regEx = new RegExp(query, "ig");
					contentDiv.innerHTML = `<b>Content:</b><br/> ${post.post_content.replace(regEx, `<code>${query}</code>`)}`;
					postDiv.appendChild(link)
					if (post.post_excerpt !== "") {
						const excerptDiv = document.createElement('div');
						excerptDiv.innerHTML = `<b>Excerpt:</b><br/> ${post.post_excerpt.toLowerCase().includes(query.toLowerCase()) ? post.post_excerpt.replace(regEx, `<code style="background-color: yellow;">${query}</code>`) : post.post_excerpt}`;
						postDiv.appendChild(excerptDiv)
					}
					postDiv.appendChild(contentDiv)
					resultsDiv.appendChild(postDiv)
				})
				spinner.classList.add('d-none')
			}
			document.getElementById('tmy-search-results').innerHTML = `<h3 class="text-center">${response.matches} Matches</h3>${resultsDiv.innerHTML}`
		}).catch((error) => {
			console.log('error', error);
		});
}
document.getElementById('tmy-search-input').addEventListener('input', function (e) {
	e.preventDefault()
	urlParams.set('query', e.target.value);
	window.history.pushState({}, "search", "?" + urlParams.toString());
	if(e.target.value !== ""){
		debounce(render(e.target.value))
	} else {
		spinner.classList.add('d-none')
		document.getElementById('tmy-search-results').innerHTML = ""
	}
});
if (urlParams.get('query')) {
	document.getElementById('tmy-search-input').value = urlParams.get('query')
	debounce(render(urlParams.get('query')))
}
        // })