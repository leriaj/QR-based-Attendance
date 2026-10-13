const profileSearch = document.getElementById('profileSearch');
const profileSearchResults = document.getElementById('profileSearchResults');
const profileSelect = document.getElementById('profileSelect');
const statusFilter = document.getElementById('statusFilter');
const calendarDays = document.querySelectorAll('.calendar-day');

function submitProfileSelection() {
  const form = profileSelect.form;

  if (typeof form.requestSubmit === 'function') {
    form.requestSubmit();
  } else {
    form.submit();
  }
}

profileSelect.addEventListener('change', submitProfileSelection);

profileSearch.addEventListener('input', () => {
  const query = profileSearch.value.trim().toLowerCase();
  profileSearchResults.replaceChildren();

  if (!query) {
    profileSearchResults.hidden = true;
    profileSearch.setAttribute('aria-expanded', 'false');
    return;
  }

  const matches = Array.from(profileSelect.options)
    .filter(option => option.value !== '' && (option.dataset.search || '').includes(query))
    .slice(0, 8);

  profileSearchResults.hidden = false;
  profileSearch.setAttribute('aria-expanded', 'true');

  if (!matches.length) {
    const emptyMessage = document.createElement('p');
    emptyMessage.className = 'profile-search-empty';
    emptyMessage.textContent = 'No profiles match this search.';
    profileSearchResults.append(emptyMessage);
    return;
  }

  matches.forEach((option) => {
    const result = document.createElement('button');
    result.className = 'profile-search-result';
    result.type = 'button';
    result.setAttribute('role', 'option');
    result.textContent = option.textContent.trim();
    result.addEventListener('click', () => {
      profileSelect.value = option.value;
      profileSearchResults.hidden = true;
      profileSearch.setAttribute('aria-expanded', 'false');
      submitProfileSelection();
    });
    profileSearchResults.append(result);
  });
});

profileSearch.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    profileSearchResults.hidden = true;
    profileSearch.setAttribute('aria-expanded', 'false');
  }

  if (event.key === 'Enter') {
    const firstResult = profileSearchResults.querySelector('.profile-search-result');
    if (firstResult) {
      event.preventDefault();
      firstResult.click();
    }
  }
});

statusFilter.addEventListener('change', () => {
  const selectedStatus = statusFilter.value;

  calendarDays.forEach((day) => {
    day.classList.toggle(
      'calendar-day-filtered',
      selectedStatus !== 'all' && day.dataset.dayStatus !== selectedStatus
    );
  });
});