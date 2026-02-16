/**
 * API client for Skills Exchange. Uses same-origin /api when served from same host.
 */
(function () {
  const API_BASE = window.API_BASE || '';

  function getToken() {
    return localStorage.getItem('token');
  }

  function setToken(token) {
    if (token) localStorage.setItem('token', token);
    else localStorage.removeItem('token');
  }

  function getHeaders(useAuth = true) {
    const h = { 'Content-Type': 'application/json' };
    if (useAuth && getToken()) h['Authorization'] = 'Bearer ' + getToken();
    return h;
  }

  async function request(method, path, body = null, useAuth = true) {
    const opts = { method, headers: getHeaders(useAuth) };
    if (body && (method === 'POST' || method === 'PUT' || method === 'PATCH')) opts.body = JSON.stringify(body);
    const res = await fetch(API_BASE + path, opts);
    const text = await res.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch (_) {}
    if (!res.ok) throw { status: res.status, data: data || { error: text } };
    return data;
  }

  window.api = {
    getToken,
    setToken,
    get base() { return API_BASE; },

    auth: {
      register: (body) => request('POST', '/api/auth/register', body, false),
      login: (body) => request('POST', '/api/auth/login', body, false),
      logout: () => request('POST', '/api/auth/logout', null, true),
    },
    categories: () => request('GET', '/api/categories'),
    skills: (categoryId) => request('GET', '/api/skills' + (categoryId ? '?category_id=' + categoryId : '')),
    createSkill: (body) => request('POST', '/api/skills', body),

    users: {
      me: () => request('GET', '/api/users/me'),
      updateMe: (body) => request('PUT', '/api/users/me', body),
      get: (id) => request('GET', '/api/users/' + id),
      search: (teachId, learnId) => request('GET', '/api/users/search?teach=' + teachId + '&learn=' + learnId),
      mySkills: () => request('GET', '/api/users/me/skills'),
      addSkill: (body) => request('POST', '/api/users/me/skills', body),
      removeSkill: (skillId) => request('DELETE', '/api/users/me/skills/' + skillId),
      uploadAvatar: async (file) => {
        const fd = new FormData();
        fd.append('avatar', file);
        const h = { Authorization: 'Bearer ' + getToken() };
        const res = await fetch(API_BASE + '/api/users/me/avatar', { method: 'POST', headers: h, body: fd });
        const text = await res.text();
        let data = null;
        try { data = text ? JSON.parse(text) : null; } catch (_) {}
        if (!res.ok) throw { status: res.status, data: data || { error: text } };
        return data;
      },
    },

    messages: {
      list: () => request('GET', '/api/messages'),
      with: (userId) => request('GET', '/api/messages/' + userId),
      send: (receiverId, content) => request('POST', '/api/messages', { receiver_id: receiverId, content }),
    },

    reviews: {
      forUser: (userId) => request('GET', '/api/reviews/' + userId),
      create: (body) => request('POST', '/api/reviews', body),
    },
  };
})();
