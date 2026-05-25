(() => {
  const cfg = window.TABLESPOT_CONFIG || null;
  const restaurantContext = window.TABLESPOT_RESTAURANT_CONTEXT || {};

  if (!cfg || !document.getElementById('booking-form')) return;
  const appBaseUrl = cfg.appBaseUrl || '';

  const bookingForm = document.getElementById('booking-form');
  const bookingDateEl = document.getElementById('booking_date');
  const timeStartEl = document.getElementById('time_start');
  const timeEndEl = document.getElementById('time_end');
  const guestsCountEl = document.getElementById('guests_count');
  const tablesContainer = document.getElementById('tables-container');
  const tablesStatusEl = document.getElementById('tables-status');
  const selectedTableIdEl = document.getElementById('selected_table_id');
  const bookBtn = document.getElementById('book-btn');
  const errorEl = document.getElementById('booking-error');

  const guestPhoneEl = document.getElementById('guest_phone');
  const guestNameEl = document.getElementById('guest_name');

  const pad2 = (n) => String(n).padStart(2, '0');
  const minutesFromTime = (hhmm) => {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
  };
  const timeFromMinutes = (mins) => {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return `${pad2(h)}:${pad2(m)}`;
  };

  const normalizeWorkHour = (t) => {
    // t может быть "10:00:00" или "10:00"
    const match = String(t).match(/^(\d{2}:\d{2})/);
    return match ? match[1] : '10:00';
  };

  const stepMinutes = 30;
  const workStart = minutesFromTime(normalizeWorkHour(cfg.workHoursStart));
  const workEnd = minutesFromTime(normalizeWorkHour(cfg.workHoursEnd));

  function todayISO() {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = pad2(d.getMonth() + 1);
    const dd = pad2(d.getDate());
    return `${yyyy}-${mm}-${dd}`;
  }

  function setLoading(message) {
    tablesStatusEl.textContent = message || '';
  }

  function renderSkeleton() {
    tablesContainer.innerHTML = `<div class="skeleton">Выберите дату и время.</div>`;
  }

  function clearError() {
    if (errorEl) errorEl.textContent = '';
  }

  function validateGuestPhone(phone) {
    // Допускаем + и цифры
    return /^[0-9+][0-9]{6,20}$/.test(phone);
  }

  function setBookButtonState() {
    const hasTable = selectedTableIdEl.value !== '';
    bookBtn.disabled = !hasTable;
  }

  function buildTimeOptions() {
    const times = [];
    for (let m = workStart; m <= workEnd; m += stepMinutes) {
      times.push(timeFromMinutes(m));
    }
    return times;
  }

  const timeOptions = buildTimeOptions();

  function populateTimeStart() {
    timeStartEl.innerHTML = '';
    const options = [];
    // start не может быть равным workEnd
    for (const t of timeOptions) {
      if (minutesFromTime(t) < workEnd) options.push(t);
    }
    options.forEach((t) => {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = t;
      timeStartEl.appendChild(opt);
    });
  }

  function populateTimeEndForStart(startTime) {
    timeEndEl.innerHTML = '';
    const startMins = minutesFromTime(startTime);
    for (const t of timeOptions) {
      const endMins = minutesFromTime(t);
      if (endMins > startMins && endMins <= workEnd) {
        const opt = document.createElement('option');
        opt.value = t;
        opt.textContent = t;
        timeEndEl.appendChild(opt);
      }
    }
  }

  function setDefaults() {
    const minDate = todayISO();
    bookingDateEl.min = minDate;
    bookingDateEl.value = bookingDateEl.value || minDate;

    // старт по умолчанию: первый слот
    const defaultStart = timeOptions[0];
    populateTimeStart();
    timeStartEl.value = defaultStart;

    // end по умолчанию: +1 час
    populateTimeEndForStart(defaultStart);
    const startM = minutesFromTime(defaultStart);
    const desiredEnd = timeFromMinutes(startM + 60);
    if ([...timeEndEl.options].some((o) => o.value === desiredEnd)) {
      timeEndEl.value = desiredEnd;
    }

    const g = restaurantContext.defaultGuestsCount || 2;
    if (guestsCountEl) guestsCountEl.value = String(g);
  }

  async function fetchAvailableTables() {
    clearError();
    setBookButtonState();
    selectedTableIdEl.value = '';

    const restaurant_id = String(cfg.restaurantId);
    const booking_date = bookingDateEl.value;
    const time_start = timeStartEl.value;
    const time_end = timeEndEl.value;
    const guests_count = guestsCountEl.value;

    if (!booking_date || !time_start || !time_end || !guests_count) {
      renderSkeleton();
      return;
    }

    setLoading('Ищем доступные столики...');
    tablesContainer.innerHTML = `<div class="skeleton">Загрузка...</div>`;

    try {
      const body = new URLSearchParams({
        csrf_token: cfg.csrfToken,
        restaurant_id,
        booking_date,
        time_start,
        time_end,
        guests_count,
      });

      const res = await fetch(`${appBaseUrl}/api/get-available-tables.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
      });

      const data = await res.json();
      if (!data.success) {
        setLoading('');
        tablesStatusEl.textContent = '';
        tablesContainer.innerHTML = `<div class="form-error">${data.error || 'Не удалось загрузить доступность.'}</div>`;
        return;
      }

      const tables = data.tables || [];
      if (tables.length === 0) {
        setLoading('');
        tablesContainer.innerHTML = `<div class="empty">Нет свободных столиков на выбранное время.</div>`;
        return;
      }

      setLoading('');
      renderTables(tables);
    } catch (err) {
      setLoading('');
      tablesContainer.innerHTML = `<div class="form-error">Ошибка сети. Попробуйте позже.</div>`;
    }
  }

  function renderTables(tables) {
    tablesContainer.innerHTML = '';
    tables.forEach((t) => {
      const el = document.createElement('div');
      el.className = 'table-option';
      el.tabIndex = 0;
      el.dataset.tableId = String(t.table_id);
      el.innerHTML = `
        <strong>Столик №${t.table_number}</strong>
        <div class="muted">Вместимость: ${t.capacity}</div>
      `;

      el.addEventListener('click', () => {
        // Делаем выбранным один вариант
        [...tablesContainer.querySelectorAll('.table-option')].forEach((x) => x.classList.remove('selected'));
        el.classList.add('selected');
        selectedTableIdEl.value = el.dataset.tableId;
        setBookButtonState();
      });

      el.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          el.click();
        }
      });

      tablesContainer.appendChild(el);
    });
  }

  // Изменение start/end влияет на доступные end-варианты
  timeStartEl.addEventListener('change', () => {
    populateTimeEndForStart(timeStartEl.value);
    // Подстраиваем end на первый доступный вариант
    if (timeEndEl.options.length > 0) timeEndEl.value = timeEndEl.options[0].value;
    fetchAvailableTables();
  });

  [bookingDateEl, timeEndEl, guestsCountEl].forEach((el) => {
    el.addEventListener('change', () => fetchAvailableTables());
  });

  bookingForm.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    clearError();

    const tableId = selectedTableIdEl.value;
    if (!tableId) {
      errorEl.textContent = 'Выберите столик.';
      return;
    }

    if (!cfg.isUserLogged) {
      const phone = guestPhoneEl ? String(guestPhoneEl.value || '').trim() : '';
      const name = guestNameEl ? String(guestNameEl.value || '').trim() : '';
      if (!validateGuestPhone(phone)) {
        errorEl.textContent = 'Некорректный телефон.';
        return;
      }
      if (name.length > 100) {
        errorEl.textContent = 'Слишком длинное имя.';
        return;
      }
      if (guestPhoneEl) guestPhoneEl.value = phone;
    }

    const payload = new URLSearchParams({
      csrf_token: cfg.csrfToken,
      restaurant_id: cfg.restaurantId,
      table_id: tableId,
      booking_date: bookingDateEl.value,
      time_start: timeStartEl.value,
      time_end: timeEndEl.value,
      guests_count: guestsCountEl.value,
    });
    if (!cfg.isUserLogged) {
      payload.set('guest_name', guestNameEl ? guestNameEl.value : '');
      payload.set('guest_phone', guestPhoneEl ? guestPhoneEl.value : '');
    }

    bookBtn.disabled = true;
    bookBtn.textContent = 'Оформляем...';

    try {
      const res = await fetch(`${appBaseUrl}/api/create-booking.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload,
      });
      const data = await res.json();
      if (!data.success) {
        errorEl.textContent = data.error || 'Не удалось оформить бронирование.';
        bookBtn.disabled = false;
        bookBtn.textContent = 'Забронировать';
        return;
      }

      const bookingId = data.booking_id;
      window.location.href = `${appBaseUrl}/booking-success.php?booking_id=${encodeURIComponent(bookingId)}`;
    } catch (err) {
      errorEl.textContent = 'Ошибка сети. Попробуйте ещё раз.';
      bookBtn.disabled = false;
      bookBtn.textContent = 'Забронировать';
    }
  });

  // Старт
  setDefaults();
  renderSkeleton();
  fetchAvailableTables();
})();

