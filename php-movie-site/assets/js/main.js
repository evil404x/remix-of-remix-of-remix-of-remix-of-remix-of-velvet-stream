/**
 * CineGold v5.0 - Main JavaScript
 * Audio Interaction Layer, Sound FX, AJAX, Inbox Logic, Security
 */

// ============ AUDIO INTERACTION LAYER ============
// Solves autoplay restrictions - unlocks audio on first user interaction anywhere
const CineSound = (function() {
    let audioCtx = null;
    let enabled = localStorage.getItem('ui_sounds') !== 'off';
    let unlocked = false;
    const volume = 0.25;

    function getCtx() {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        return audioCtx;
    }

    function unlock() {
        if (unlocked) return;
        try {
            const ctx = getCtx();
            const buffer = ctx.createBuffer(1, 1, 22050);
            const source = ctx.createBufferSource();
            source.buffer = buffer;
            source.connect(ctx.destination);
            source.start(0);
            unlocked = true;
            console.log('[CineSound] Audio unlocked');
        } catch(e) {}
    }

    // Soft click
    function playClick() {
        if (!enabled) return;
        try {
            const ctx = getCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(800, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(600, ctx.currentTime + 0.06);
            gain.gain.setValueAtTime(volume * 0.4, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.08);
        } catch(e) {}
    }

    // Notification ping
    function playNotification() {
        if (!enabled) return;
        try {
            const ctx = getCtx();
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(880, ctx.currentTime);
            osc1.frequency.setValueAtTime(1100, ctx.currentTime + 0.1);
            gain1.gain.setValueAtTime(volume * 0.5, ctx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc1.start(ctx.currentTime);
            osc1.stop(ctx.currentTime + 0.3);
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(1320, ctx.currentTime + 0.08);
            gain2.gain.setValueAtTime(0, ctx.currentTime);
            gain2.gain.setValueAtTime(volume * 0.35, ctx.currentTime + 0.08);
            gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
            osc2.start(ctx.currentTime + 0.08);
            osc2.stop(ctx.currentTime + 0.35);
        } catch(e) {}
    }

    // Success chime
    function playSuccess() {
        if (!enabled) return;
        try {
            const ctx = getCtx();
            [523, 659, 784].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, ctx.currentTime + i * 0.1);
                gain.gain.setValueAtTime(0, ctx.currentTime + i * 0.1);
                gain.gain.linearRampToValueAtTime(volume * 0.45, ctx.currentTime + i * 0.1 + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.1 + 0.25);
                osc.start(ctx.currentTime + i * 0.1);
                osc.stop(ctx.currentTime + i * 0.1 + 0.25);
            });
        } catch(e) {}
    }

    // Swoosh sound (message sent)
    function playSwoosh() {
        if (!enabled) return;
        try {
            const ctx = getCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(400, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1200, ctx.currentTime + 0.12);
            osc.frequency.exponentialRampToValueAtTime(800, ctx.currentTime + 0.2);
            gain.gain.setValueAtTime(volume * 0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.22);
        } catch(e) {}
    }

    // Pop sound (message received)
    function playPop() {
        if (!enabled) return;
        try {
            const ctx = getCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1200, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(600, ctx.currentTime + 0.1);
            gain.gain.setValueAtTime(volume * 0.4, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.15);
        } catch(e) {}
    }

    function setEnabled(val) { enabled = val; localStorage.setItem('ui_sounds', val ? 'on' : 'off'); }
    function isEnabled() { return enabled; }
    function isUnlocked() { return unlocked; }

    return { click: playClick, notification: playNotification, success: playSuccess, swoosh: playSwoosh, pop: playPop, setEnabled, isEnabled, isUnlocked, unlock };
})();

// ============ AUDIO INTERACTION LAYER - Unlock on ANY first interaction ============
['click', 'touchstart', 'keydown'].forEach(evt => {
    document.addEventListener(evt, function _unlock() {
        CineSound.unlock();
        document.removeEventListener(evt, _unlock);
    }, { once: true, passive: true });
});

document.addEventListener('DOMContentLoaded', function() {

    // ============ GLOBAL CLICK SOUND ============
    document.addEventListener('click', function(e) {
        const target = e.target.closest('button, a, .btn, .movie-card, .profile-tab, .inbox-cat-tab, .mobile-bar-item, .smart-hub-btn, .tag, .server-btn, .season-btn, .episode-card, .lang-tab, .filter-select');
        if (target) CineSound.click();
    });

    // ============ PRELOADER ============
    const preloader = document.querySelector('.preloader');
    if (preloader) {
        window.addEventListener('load', () => {
            setTimeout(() => preloader.classList.add('hidden'), 600);
        });
        setTimeout(() => preloader.classList.add('hidden'), 4000);
    }

    // ============ NAVBAR SCROLL ============
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 60);
        });
        if (window.scrollY > 60) navbar.classList.add('scrolled');
    }

    // ============ MOBILE MENU ============
    const mobileToggle = document.querySelector('.mobile-toggle');
    const navLinks = document.querySelector('.nav-links');
    const overlay = document.querySelector('.mobile-menu-overlay');
    
    function openMobileMenu() {
        navLinks.classList.add('show');
        if (overlay) {
            overlay.style.display = 'block';
            requestAnimationFrame(() => overlay.classList.add('show'));
        }
        document.body.style.overflow = 'hidden';
        mobileToggle.innerHTML = '<i class="fas fa-times"></i>';
    }
    
    function closeMobileMenu() {
        navLinks.classList.remove('show');
        if (overlay) {
            overlay.classList.remove('show');
            setTimeout(() => overlay.style.display = 'none', 300);
        }
        document.body.style.overflow = '';
        mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
    }

    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            navLinks.classList.contains('show') ? closeMobileMenu() : openMobileMenu();
        });
        if (overlay) overlay.addEventListener('click', closeMobileMenu);
        navLinks.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMobileMenu));
    }

    // ============ MULTI-LANGUAGE VIDEO PLAYER ============
    const langTabs = document.querySelectorAll('.lang-tab');
    const serverContainers = document.querySelectorAll('.servers-group');

    langTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const lang = this.dataset.lang;
            langTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            serverContainers.forEach(group => {
                group.style.display = group.dataset.lang === lang ? 'flex' : 'none';
            });
            const firstServer = document.querySelector(`.servers-group[data-lang="${lang}"] .server-btn`);
            if (firstServer) firstServer.click();
        });
    });

    // Server switching with encrypted links
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('server-btn') || e.target.closest('.server-btn')) {
            const btn = e.target.classList.contains('server-btn') ? e.target : e.target.closest('.server-btn');
            const token = btn.dataset.token;
            const url = btn.dataset.url;
            const videoWrapper = document.querySelector('.video-wrapper');
            
            document.querySelectorAll('.server-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            if (videoWrapper) {
                if (token) {
                    fetch(`${window.SITE_URL}/api/decrypt-link.php?token=${encodeURIComponent(token)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.url) {
                                videoWrapper.innerHTML = `<iframe src="${data.url}" allowfullscreen allow="autoplay; encrypted-media" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" referrerpolicy="no-referrer" frameborder="0"></iframe>`;
                            }
                        })
                        .catch(() => {
                            if (url) videoWrapper.innerHTML = `<iframe src="${url}" allowfullscreen allow="autoplay; encrypted-media" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" referrerpolicy="no-referrer" frameborder="0"></iframe>`;
                        });
                } else if (url) {
                    videoWrapper.innerHTML = `<iframe src="${url}" allowfullscreen allow="autoplay; encrypted-media" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" referrerpolicy="no-referrer" frameborder="0"></iframe>`;
                }
            }
        }
    });

    // ============ SEASONS & EPISODES ============
    const seasonBtns = document.querySelectorAll('.season-btn');
    const episodeContainers = document.querySelectorAll('.episodes-container');

    seasonBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const season = this.dataset.season;
            seasonBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            episodeContainers.forEach(container => {
                container.style.display = container.dataset.season === season ? 'grid' : 'none';
            });
        });
    });

    document.addEventListener('click', function(e) {
        const epCard = e.target.closest('.episode-card');
        if (epCard) {
            document.querySelectorAll('.episode-card').forEach(c => c.classList.remove('active'));
            epCard.classList.add('active');
            
            const movieId = epCard.dataset.movieId;
            const season = epCard.dataset.season;
            const episode = epCard.dataset.episode;
            
            fetch(`${window.SITE_URL}/api/get-links.php?movie_id=${movieId}&season=${season}&episode=${episode}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.links.length > 0) {
                        updatePlayerLinks(data.links);
                        document.getElementById('player-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                })
                .catch(err => console.error('Error loading episode:', err));
        }
    });

    function updatePlayerLinks(links) {
        const grouped = {};
        links.forEach(link => {
            if (!grouped[link.language]) grouped[link.language] = [];
            grouped[link.language].push(link);
        });

        Object.keys(grouped).forEach(lang => {
            const container = document.querySelector(`.servers-group[data-lang="${lang}"]`);
            if (container) {
                container.innerHTML = grouped[lang].map((link, i) => {
                    const attr = link.encrypted_url 
                        ? `data-token="${link.encrypted_url}"` 
                        : `data-url="${link.video_url}"`;
                    return `<button class="server-btn ${i === 0 ? 'active' : ''}" ${attr}><i class="fas fa-server"></i> ${link.server_name}</button>`;
                }).join('');
                container.style.display = container.dataset.lang === document.querySelector('.lang-tab.active')?.dataset.lang ? 'flex' : 'none';
            }
        });

        if (links.length > 0) {
            const videoWrapper = document.querySelector('.video-wrapper');
            const firstUrl = links[0].video_url || '';
            if (videoWrapper && firstUrl) {
                videoWrapper.innerHTML = `<iframe src="${firstUrl}" allowfullscreen allow="autoplay; encrypted-media" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" referrerpolicy="no-referrer" frameborder="0"></iframe>`;
            }
        }
    }

    // ============ FAVORITE TOGGLE ============
    document.addEventListener('click', function(e) {
        const favBtn = e.target.closest('.fav-btn');
        if (favBtn) {
            e.preventDefault();
            const movieId = favBtn.dataset.movieId;
            fetch(`${window.SITE_URL}/api/toggle-favorite.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ movie_id: movieId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    favBtn.classList.toggle('active', data.is_favorited);
                    favBtn.innerHTML = data.is_favorited ? '❤️ دڵخواز' : '🤍 دڵخواز';
                    if (data.is_favorited) CineSound.success();
                } else if (data.login_required) {
                    window.location.href = `${window.SITE_URL}/login.php`;
                }
            });
        }
    });

    // ============ LIVE SEARCH (Movies + Users) ============
    const searchInput = document.querySelector('.nav-search input');
    const searchResults = document.querySelector('.search-dropdown');
    let searchTimeout;

    if (searchInput && searchResults) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) { searchResults.style.display = 'none'; return; }

            searchTimeout = setTimeout(() => {
                Promise.all([
                    fetch(`${window.SITE_URL}/api/search.php?q=${encodeURIComponent(query)}`).then(r=>r.json()),
                    fetch(`${window.SITE_URL}/api/user-search.php?q=${encodeURIComponent(query)}`).then(r=>r.json()).catch(()=>({results:[]}))
                ]).then(([movieData, userData]) => {
                    let html = '';
                    if (userData.results && userData.results.length > 0) {
                        html += '<div style="padding:8px 12px;font-size:0.75rem;color:var(--gold);font-weight:600;border-bottom:1px solid rgba(255,255,255,0.04);"><i class="fas fa-users"></i> بەکارهێنەران</div>';
                        html += userData.results.map(u => `
                            <a href="${window.SITE_URL}/profile.php?id=${u.id}" class="search-result-item">
                                <i class="fas fa-user-circle" style="font-size:1.6rem;color:var(--gray);"></i>
                                <div><h4><span class="dm-status-dot-inline ${u.is_online ? 'online' : 'offline'}"></span> ${u.username}</h4></div>
                            </a>
                        `).join('');
                    }
                    if (movieData.results && movieData.results.length > 0) {
                        if (html) html += '<div style="padding:8px 12px;font-size:0.75rem;color:var(--gold);font-weight:600;border-bottom:1px solid rgba(255,255,255,0.04);"><i class="fas fa-film"></i> فیلمەکان</div>';
                        html += movieData.results.map(movie => `
                            <a href="${window.SITE_URL}/movie/${movie.slug}" class="search-result-item">
                                <img src="${window.SITE_URL}/uploads/posters/${movie.poster || 'default.jpg'}" alt="${movie.title}">
                                <div><h4>${movie.title}</h4><span>${movie.release_year} • ⭐ ${movie.imdb_rate}</span></div>
                            </a>
                        `).join('');
                    }
                    if (!html) html = '<p class="no-results">هیچ ئەنجامێک نەدۆزرایەوە</p>';
                    searchResults.innerHTML = html;
                    searchResults.style.display = 'block';
                });
            }, 300);
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.nav-search')) searchResults.style.display = 'none';
        });
    }

    // ============ NOTIFICATION TOGGLE ============
    const notifBtn = document.querySelector('.notif-btn');
    const notifDropdown = document.querySelector('.notification-dropdown');
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.notification-dropdown')) notifDropdown.classList.remove('show');
        });
    }

    // ============ AD POPUP ============
    const adPopup = document.querySelector('.ad-popup');
    const adOverlay2 = document.querySelector('.ad-overlay');
    const adClose = document.querySelector('.ad-close');

    if (adPopup && adOverlay2) {
        setTimeout(() => {
            adPopup.classList.add('show');
            adOverlay2.style.display = 'block';
            requestAnimationFrame(() => adOverlay2.classList.add('show'));
            const adId = adPopup.dataset.adId;
            if (adId) fetch(`${window.SITE_URL}/api/ad-impression.php?id=${adId}`).catch(() => {});
        }, 4000);
    }

    function closeAd() {
        if (adPopup) adPopup.classList.remove('show');
        if (adOverlay2) {
            adOverlay2.classList.remove('show');
            setTimeout(() => adOverlay2.style.display = 'none', 300);
        }
    }

    if (adClose) adClose.addEventListener('click', closeAd);
    if (adOverlay2) adOverlay2.addEventListener('click', closeAd);

    // ============ SCROLL ANIMATIONS ============
    const observerOptions = { threshold: 0.08, rootMargin: '0px 0px -40px 0px' };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                }, index * 50);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.movie-card, .stat-card, .section-title, .panel-card, .comment-card').forEach(el => {
        el.style.opacity = '0';
        observer.observe(el);
    });

    // ============ IMAGE LAZY LOADING ============
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) { img.src = img.dataset.src; img.removeAttribute('data-src'); }
                    imageObserver.unobserve(img);
                }
            });
        }, { rootMargin: '100px' });
        document.querySelectorAll('img[data-src]').forEach(img => imageObserver.observe(img));
    }

    // ============ ADMIN SIDEBAR TOGGLE ============
    const adminToggle = document.querySelector('.admin-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    
    if (adminSidebar) {
        let sidebarOverlay = document.querySelector('.admin-sidebar-overlay');
        if (!sidebarOverlay) {
            sidebarOverlay = document.createElement('div');
            sidebarOverlay.className = 'admin-sidebar-overlay';
            document.body.appendChild(sidebarOverlay);
        }
        
        function openAdminSidebar() { adminSidebar.classList.add('show'); sidebarOverlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
        function closeAdminSidebar() { adminSidebar.classList.remove('show'); sidebarOverlay.classList.remove('show'); document.body.style.overflow = ''; }
        
        if (adminToggle) adminToggle.addEventListener('click', () => { adminSidebar.classList.contains('show') ? closeAdminSidebar() : openAdminSidebar(); });
        sidebarOverlay.addEventListener('click', closeAdminSidebar);
        adminSidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', closeAdminSidebar));
    }

    // ============ REPORT FORM (Modal) ============
    document.addEventListener('click', function(e) {
        const reportBtn = e.target.closest('.report-btn');
        if (reportBtn) {
            const movieId = reportBtn.dataset.movieId;
            let modal = document.getElementById('report-modal');
            if (modal) modal.remove();
            
            modal = document.createElement('div');
            modal.id = 'report-modal';
            modal.innerHTML = `
                <div class="report-modal-overlay"></div>
                <div class="report-modal-content glass-strong">
                    <button class="report-modal-close">&times;</button>
                    <h3 style="color:var(--gold);margin-bottom:18px;font-size:1.1rem;"><i class="fas fa-flag"></i> ڕاپۆرتی کێشە</h3>
                    <div class="report-reasons">
                        <label class="report-reason-option"><input type="radio" name="report_reason" value="لینک کار ناکات"> <span>لینک کار ناکات</span></label>
                        <label class="report-reason-option"><input type="radio" name="report_reason" value="کوالیتی خراپ"> <span>کوالیتی خراپ</span></label>
                        <label class="report-reason-option"><input type="radio" name="report_reason" value="زمانی هەڵە"> <span>زمانی هەڵە</span></label>
                        <label class="report-reason-option"><input type="radio" name="report_reason" value="ناوەڕۆکی نەگونجاو"> <span>ناوەڕۆکی نەگونجاو</span></label>
                    </div>
                    <textarea id="report-extra" placeholder="تێبینی زیاتر (ئارەزوومەندانە)..." style="width:100%;min-height:70px;margin-top:12px;padding:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;color:var(--white);font-family:inherit;font-size:16px;resize:vertical;outline:none;"></textarea>
                    <button id="report-submit" class="btn btn-gold btn-sm" style="margin-top:14px;width:100%;justify-content:center;">
                        <i class="fas fa-paper-plane"></i> ناردنی ڕاپۆرت
                    </button>
                </div>
            `;
            document.body.appendChild(modal);
            requestAnimationFrame(() => modal.classList.add('show'));

            modal.querySelector('.report-modal-overlay').addEventListener('click', () => { modal.classList.remove('show'); setTimeout(() => modal.remove(), 300); });
            modal.querySelector('.report-modal-close').addEventListener('click', () => { modal.classList.remove('show'); setTimeout(() => modal.remove(), 300); });

            modal.querySelector('#report-submit').addEventListener('click', () => {
                const selected = modal.querySelector('input[name="report_reason"]:checked');
                const extra = modal.querySelector('#report-extra').value.trim();
                const reason = (selected ? selected.value : '') + (extra ? ' - ' + extra : '');
                if (!reason.trim()) return;
                
                fetch(`${window.SITE_URL}/api/report.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ movie_id: movieId, reason: reason.trim() })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        CineSound.success();
                        modal.querySelector('.report-modal-content').innerHTML = '<div style="text-align:center;padding:30px;"><i class="fas fa-check-circle" style="font-size:2.5rem;color:var(--green);margin-bottom:12px;"></i><p style="color:var(--white);">ڕاپۆرتەکەت بە سەرکەوتوویی نێردرا. سوپاس!</p></div>';
                        setTimeout(() => { modal.classList.remove('show'); setTimeout(() => modal.remove(), 300); }, 2000);
                    } else { alert('هەڵەیەک ڕوویدا.'); }
                })
                .catch(() => alert('هەڵەیەک ڕوویدا.'));
            });
        }
    });

    // ============ COMMENT FORM WITH SPOILER ============
    const commentForm = document.querySelector('.comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const spoilerCheck = this.querySelector('input[name="is_spoiler"]');
            if (spoilerCheck && !spoilerCheck.checked) formData.set('is_spoiler', '0');
            fetch(`${window.SITE_URL}/api/comment.php`, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) { CineSound.success(); location.reload(); }
                else alert(data.message || 'هەڵەیەک ڕوویدا.');
            });
        });
    }

    // ============ NEXT/PREVIOUS EPISODE ============
    let currentEpisodeData = { season: null, episode: null };
    const epNav = document.getElementById('episode-nav');
    const prevBtn = document.getElementById('prev-episode');
    const nextBtn = document.getElementById('next-episode');
    const epInfo = document.getElementById('current-episode-info');
    
    document.addEventListener('click', function(e) {
        const epCard = e.target.closest('.episode-card');
        if (epCard && epNav) {
            currentEpisodeData.season = parseInt(epCard.dataset.season);
            currentEpisodeData.episode = parseInt(epCard.dataset.episode);
            epNav.style.display = 'flex';
            epInfo.textContent = `وەرز ${currentEpisodeData.season} - ئەڵقە ${currentEpisodeData.episode}`;
            const allEps = Array.from(document.querySelectorAll(`.episodes-container[data-season="${currentEpisodeData.season}"] .episode-card`));
            const currentIdx = allEps.findIndex(c => parseInt(c.dataset.episode) === currentEpisodeData.episode);
            if (prevBtn) prevBtn.disabled = currentIdx <= 0;
            if (nextBtn) nextBtn.disabled = currentIdx >= allEps.length - 1;
        }
    });
    
    if (prevBtn) prevBtn.addEventListener('click', () => navigateEpisode(-1));
    if (nextBtn) nextBtn.addEventListener('click', () => navigateEpisode(1));
    
    function navigateEpisode(direction) {
        const allEps = Array.from(document.querySelectorAll(`.episodes-container[data-season="${currentEpisodeData.season}"] .episode-card`));
        const currentIdx = allEps.findIndex(c => parseInt(c.dataset.episode) === currentEpisodeData.episode);
        const targetIdx = currentIdx + direction;
        if (targetIdx >= 0 && targetIdx < allEps.length) allEps[targetIdx].click();
    }

    // ============ WATCH PARTY SYSTEM ============
    const wpCreateBtn = document.getElementById('wp-create-btn');
    const wpChatForm = document.getElementById('wp-chat-form');
    const wpChatMessages = document.getElementById('wp-chat-messages');
    let wpLastMsgId = 0;
    let wpSyncInterval = null;
    let wpChatInterval = null;
    let wpMembersInterval = null;
    let wpHeartbeatInterval = null;

    const urlParams = new URLSearchParams(window.location.search);
    const wpRoomId = urlParams.get('party');

    if (wpCreateBtn) {
        wpCreateBtn.addEventListener('click', function() {
            const movieId = this.dataset.movieId;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> چاوەڕوان بە...';
            
            fetch(`${window.SITE_URL}/api/watch-party.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'create', movie_id: parseInt(movieId) })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    CineSound.success();
                    let slug = urlParams.get('slug');
                    if (!slug) {
                        const pathParts = window.location.pathname.split('/movie/');
                        if (pathParts.length > 1) slug = pathParts[1].replace(/\/$/, '');
                    }
                    const partyUrl = slug 
                        ? `${window.SITE_URL}/movie.php?slug=${slug}&party=${data.room_id}`
                        : `${window.location.href}${window.location.search ? '&' : '?'}party=${data.room_id}`;
                    window.location.href = partyUrl;
                } else {
                    alert(data.message === 'login_required' ? 'تکایە سەرەتا لۆگین بکە!' : 'هەڵە: ' + (data.message || 'نەزانراو'));
                    wpCreateBtn.disabled = false;
                    wpCreateBtn.innerHTML = '<i class="fas fa-plus-circle"></i> دروستکردنی ژووری Watch Party';
                }
            })
            .catch(err => {
                alert('هەڵە لە پەیوەندیکردن بە سێرڤەرەوە.');
                wpCreateBtn.disabled = false;
                wpCreateBtn.innerHTML = '<i class="fas fa-plus-circle"></i> دروستکردنی ژووری Watch Party';
            });
        });
    }

    // Copy room link
    const wpCopyRoomLink = document.getElementById('wp-copy-room-link');
    if (wpCopyRoomLink) {
        wpCopyRoomLink.addEventListener('click', function() {
            navigator.clipboard.writeText(this.dataset.link).then(() => {
                this.innerHTML = '<i class="fas fa-check"></i> کۆپی کرا!';
                CineSound.success();
                setTimeout(() => this.innerHTML = '<i class="fas fa-copy"></i> کۆپی لینک', 2000);
            });
        });
    }

    // Leave room
    const wpLeaveBtn = document.getElementById('wp-leave-btn');
    if (wpLeaveBtn && wpRoomId) {
        wpLeaveBtn.addEventListener('click', function() {
            if (!confirm('دڵنیایت دەتەوێت لە ژوورەکە بچیتە دەرەوە؟')) return;
            fetch(`${window.SITE_URL}/api/watch-party.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'leave', room_id: wpRoomId })
            }).then(() => {
                const url = new URL(window.location.href);
                url.searchParams.delete('party');
                window.location.href = url.toString();
            });
        });
    }

    // Members list
    function wpLoadMembers() {
        if (!wpRoomId) return;
        fetch(`${window.SITE_URL}/api/watch-party.php?action=members&room_id=${wpRoomId}`, { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const list = document.getElementById('wp-members-list');
                const countEl = document.getElementById('wp-member-count');
                const hostNameEl = document.getElementById('wp-host-name');
                if (!list) return;

                countEl.textContent = data.members.length;
                list.innerHTML = '';
                
                data.members.forEach(member => {
                    const div = document.createElement('div');
                    div.className = 'wp-member-item';
                    const isHost = member.is_host;
                    const isCurrentUser = (parseInt(member.user_id) === data.current_user_id);
                    const canPromote = (data.host_id === data.current_user_id) && !isHost;
                    
                    div.innerHTML = `
                        <div class="wp-member-info">
                            <i class="fas ${isHost ? 'fa-crown' : 'fa-user'}" style="color:${isHost ? 'var(--gold)' : 'var(--gray-light)'}"></i>
                            <span class="wp-member-name ${isHost ? 'text-gold' : ''}">${member.username}${isCurrentUser ? ' (تۆ)' : ''}</span>
                        </div>
                        <div class="wp-member-actions">
                            ${isHost ? '<span class="wp-host-badge">هۆست</span>' : ''}
                            ${canPromote ? `<button class="btn btn-glass btn-xs wp-promote-btn" data-user-id="${member.user_id}" data-username="${member.username}"><i class="fas fa-crown"></i></button>` : ''}
                        </div>
                    `;
                    list.appendChild(div);
                    if (isHost && hostNameEl) hostNameEl.textContent = member.username;
                });

                list.querySelectorAll('.wp-promote-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const newHostId = parseInt(this.dataset.userId);
                        const username = this.dataset.username;
                        if (!confirm(`دەتەوێت "${username}" ببێتە ئادمینی ژوورەکە؟`)) return;
                        fetch(`${window.SITE_URL}/api/watch-party.php`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ action: 'promote', room_id: wpRoomId, new_host_id: newHostId })
                        }).then(res => res.json()).then(data => {
                            if (data.success) window.location.reload();
                            else alert('هەڵە: ' + (data.message || ''));
                        });
                    });
                });
            });
    }

    // Heartbeat
    function wpSendHeartbeat() {
        if (!wpRoomId) return;
        fetch(`${window.SITE_URL}/api/watch-party.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'join', room_id: wpRoomId })
        });
    }

    // Sync & Chat
    if (wpRoomId) {
        wpSendHeartbeat();
        wpHeartbeatInterval = setInterval(wpSendHeartbeat, 5000);
        wpLoadMembers();
        wpMembersInterval = setInterval(wpLoadMembers, 3000);

        fetch(`${window.SITE_URL}/api/watch-party.php?action=info&room_id=${wpRoomId}`, { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let isHost = data.is_host;
                    if (isHost) {
                        let hostTime = 0;
                        let hostStatus = 'playing';
                        window.addEventListener('message', function(e) {
                            if (e.data && e.data.type === 'timeupdate') hostTime = e.data.currentTime;
                            if (e.data && e.data.type === 'statuschange') hostStatus = e.data.status;
                        });
                        wpSyncInterval = setInterval(() => {
                            fetch(`${window.SITE_URL}/api/watch-party.php`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                credentials: 'same-origin',
                                body: JSON.stringify({ action: 'sync', room_id: wpRoomId, current_time: hostTime, status: hostStatus })
                            });
                        }, 2000);
                        const syncStatus = document.getElementById('wp-sync-status');
                        if (syncStatus) { syncStatus.innerHTML = '<i class="fas fa-crown"></i> تۆ هۆستیت'; syncStatus.style.color = 'var(--gold)'; }
                    } else {
                        let guestTime = 0;
                        wpSyncInterval = setInterval(() => {
                            fetch(`${window.SITE_URL}/api/watch-party.php?action=poll&room_id=${wpRoomId}`, { credentials: 'same-origin' })
                                .then(res => res.json())
                                .then(state => {
                                    if (state.success) {
                                        if (state.is_host && !isHost) { window.location.reload(); return; }
                                        const diff = Math.abs(state.current_time - guestTime);
                                        const syncStatus = document.getElementById('wp-sync-status');
                                        if (diff > 2) {
                                            if (syncStatus) { syncStatus.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> سینک...'; }
                                            const iframe = document.querySelector('.video-wrapper iframe');
                                            if (iframe) { try { iframe.contentWindow.postMessage({ type: 'seek', time: state.current_time }, '*'); } catch(e) {} }
                                        } else {
                                            if (syncStatus) { syncStatus.innerHTML = '<i class="fas fa-check-circle"></i> سینک شدە'; }
                                        }
                                        guestTime = state.current_time;
                                    }
                                });
                        }, 2000);
                    }
                }
            });

        // Chat polling
        wpChatInterval = setInterval(() => {
            fetch(`${window.SITE_URL}/api/watch-party.php?action=chat_poll&room_id=${wpRoomId}&after=${wpLastMsgId}`, { credentials: 'same-origin' })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        if (wpLastMsgId === 0 && wpChatMessages) wpChatMessages.innerHTML = '';
                        data.messages.forEach(msg => {
                            wpLastMsgId = msg.id;
                            const div = document.createElement('div');
                            const isOwn = (parseInt(msg.user_id) === data.current_user_id);
                            div.className = 'wp-chat-msg' + (isOwn ? ' own' : '');
                            const time = new Date(msg.created_at).toLocaleTimeString('ku', { hour: '2-digit', minute: '2-digit' });
                            div.innerHTML = `<div class="msg-user">${msg.username}</div><div class="msg-text">${msg.message}</div><div class="msg-time">${time}</div>`;
                            wpChatMessages.appendChild(div);
                        });
                        wpChatMessages.scrollTop = wpChatMessages.scrollHeight;
                        CineSound.pop();
                    }
                });
        }, 3000);
    }

    // Send chat message
    if (wpChatForm) {
        wpChatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const input = document.getElementById('wp-chat-input');
            const message = input.value.trim();
            if (!message || !wpRoomId) return;
            CineSound.swoosh();
            fetch(`${window.SITE_URL}/api/watch-party.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'chat_send', room_id: wpRoomId, message })
            }).then(res => res.json()).then(data => { if (data.success) input.value = ''; });
        });
    }

    // Cleanup on page leave
    window.addEventListener('beforeunload', function() {
        if (wpRoomId) {
            const blob = new Blob([JSON.stringify({ action: 'leave', room_id: wpRoomId })], { type: 'application/json' });
            navigator.sendBeacon(`${window.SITE_URL}/api/watch-party.php`, blob);
        }
    });

    // ============ INVITE SYSTEM IN WATCH PARTY ============
    const wpInviteBtn = document.getElementById('wp-invite-btn');
    if (wpInviteBtn && wpRoomId) {
        wpInviteBtn.addEventListener('click', function() {
            fetch(`${window.SITE_URL}/api/invite.php?action=followers_list&room_id=${wpRoomId}`, { credentials: 'same-origin' })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) return;
                    let modal = document.getElementById('wp-invite-modal');
                    if (modal) modal.remove();
                    
                    modal = document.createElement('div');
                    modal.id = 'wp-invite-modal';
                    modal.className = 'report-modal-overlay-wrap';
                    
                    let listHtml = '';
                    if (data.followers.length === 0) {
                        listHtml = '<p class="text-gray" style="text-align:center;padding:20px;">هیچ فۆڵۆوەرێکت نییە.</p>';
                    } else {
                        data.followers.forEach(f => {
                            listHtml += `
                                <div class="wp-invite-item">
                                    <span><i class="fas fa-user"></i> ${f.username}</span>
                                    ${f.in_room ? '<span class="wp-host-badge">لە ژوورەکەدایە</span>' : 
                                    `<button class="btn btn-gold btn-xs wp-send-invite" data-user-id="${f.id}" data-username="${f.username}"><i class="fas fa-paper-plane"></i> بانگهێشت</button>`}
                                </div>
                            `;
                        });
                    }
                    
                    modal.innerHTML = `
                        <div class="report-modal-overlay" onclick="this.parentElement.remove()"></div>
                        <div class="report-modal-content glass-strong">
                            <button class="report-modal-close" onclick="this.closest('#wp-invite-modal').remove()">&times;</button>
                            <h3 style="color:var(--gold);margin-bottom:18px;"><i class="fas fa-user-plus"></i> بانگهێشتکردنی فۆڵۆوەران</h3>
                            <div class="wp-invite-list">${listHtml}</div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                    requestAnimationFrame(() => modal.classList.add('show'));
                    
                    modal.querySelectorAll('.wp-send-invite').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const inviteeId = parseInt(this.dataset.userId);
                            this.disabled = true;
                            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                            
                            fetch(`${window.SITE_URL}/api/invite.php`, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                credentials: 'same-origin',
                                body: JSON.stringify({ action: 'send', room_id: wpRoomId, invitee_id: inviteeId })
                            }).then(res => res.json()).then(data => {
                                if (data.success) {
                                    CineSound.success();
                                    this.innerHTML = '<i class="fas fa-check"></i> نێردرا';
                                    this.classList.remove('btn-gold');
                                    this.classList.add('btn-glass');
                                } else {
                                    this.innerHTML = 'هەڵە';
                                    this.disabled = false;
                                }
                            });
                        });
                    });
                });
        });
    }

    // ============ FOLLOW/UNFOLLOW SYSTEM ============
    document.addEventListener('click', function(e) {
        const followBtn = e.target.closest('.follow-btn');
        if (followBtn) {
            e.preventDefault();
            const targetUserId = parseInt(followBtn.dataset.userId);
            followBtn.disabled = true;
            
            fetch(`${window.SITE_URL}/api/follow.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'toggle', user_id: targetUserId })
            }).then(res => res.json()).then(data => {
                followBtn.disabled = false;
                if (data.success) {
                    if (data.is_following) {
                        if (data.status === 'pending') {
                            followBtn.innerHTML = '<i class="fas fa-clock"></i> چاوەڕوان';
                            followBtn.classList.remove('btn-gold'); followBtn.classList.add('btn-glass');
                        } else {
                            followBtn.innerHTML = '<i class="fas fa-user-check"></i> فۆڵۆکراوە';
                            followBtn.classList.remove('btn-gold'); followBtn.classList.add('btn-glass');
                            CineSound.success();
                        }
                    } else {
                        followBtn.innerHTML = '<i class="fas fa-user-plus"></i> فۆڵۆ';
                        followBtn.classList.remove('btn-glass'); followBtn.classList.add('btn-gold');
                    }
                    if (typeof refreshFollowCounts === 'function') refreshFollowCounts(targetUserId);
                } else if (data.message === 'login_required') {
                    window.location.href = `${window.SITE_URL}/login.php`;
                } else { alert(data.message || 'هەڵە'); }
            });
        }
    });

    // ============ PRIVACY SETTINGS ============
    const privacyForm = document.getElementById('privacy-form');
    if (privacyForm) {
        fetch(`${window.SITE_URL}/api/privacy.php?action=get`, { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('privacy-private').checked = !!data.privacy.private_account;
                    document.getElementById('privacy-hide-search').checked = !!data.privacy.hide_from_search;
                    document.getElementById('privacy-disable-follow').checked = !!data.privacy.disable_follow;
                    const hfEl = document.getElementById('privacy-hide-following');
                    const hpEl = document.getElementById('privacy-hide-points');
                    if (hfEl) hfEl.checked = !!data.privacy.hide_following;
                    if (hpEl) hpEl.checked = !!data.privacy.hide_points;
                }
            });

        privacyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = {
                action: 'update',
                private_account: document.getElementById('privacy-private').checked ? 1 : 0,
                hide_from_search: document.getElementById('privacy-hide-search').checked ? 1 : 0,
                disable_follow: document.getElementById('privacy-disable-follow').checked ? 1 : 0,
                hide_following: document.getElementById('privacy-hide-following')?.checked ? 1 : 0,
                hide_points: document.getElementById('privacy-hide-points')?.checked ? 1 : 0
            };
            
            fetch(`${window.SITE_URL}/api/privacy.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(formData)
            }).then(res => res.json()).then(data => {
                const msg = document.getElementById('privacy-msg');
                if (data.success) {
                    CineSound.success();
                    msg.innerHTML = '<i class="fas fa-check-circle"></i> ڕێکخستنەکان پاشەکەوت کران';
                    msg.className = 'alert alert-success';
                    msg.style.display = 'block';
                    setTimeout(() => msg.style.display = 'none', 3000);
                }
            });
        });
    }

    // ============ SMART HUB - REAL-TIME BADGE SYSTEM ============
    let lastHubCounts = { notifications: 0, messages: 0, followers: 0, invites: 0, total: 0 };
    let previewShown = false;

    function updateSmartHub() {
        fetch(`${window.SITE_URL}/api/hub-counts.php`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                
                const totalNew = data.followers + data.messages + data.total;
                const oldTotal = lastHubCounts.followers + lastHubCounts.messages + lastHubCounts.total;
                if (totalNew > oldTotal && oldTotal > 0 && !previewShown) {
                    showInboxPreviewPopup(data);
                    CineSound.notification();
                }
                lastHubCounts = data;
                
                // Followers badge - stays until manually cleared
                updateBadge('hub-followers-badge', data.followers);
                updateBadge('hub-messages-badge', data.messages);
                updateBadge('mobile-msg-badge', data.messages);
                const inboxTotal = data.total + data.followers;
                updateBadge('hub-notif-badge', inboxTotal);
                updateBadge('mobile-notif-badge', inboxTotal);
            }).catch(() => {});
    }

    function showInboxPreviewPopup(data) {
        previewShown = true;
        let previewEl = document.getElementById('inbox-preview-popup');
        if (!previewEl) {
            previewEl = document.createElement('div');
            previewEl.id = 'inbox-preview-popup';
            document.body.appendChild(previewEl);
        }
        
        let items = [];
        if (data.followers > 0) items.push(`<div class="preview-item"><i class="fas fa-user-plus" style="color:#FE2C55;"></i> <span>${data.followers} فۆڵۆوەری نوێ</span></div>`);
        if (data.messages > 0) items.push(`<div class="preview-item"><i class="fas fa-envelope" style="color:#25F4EE;"></i> <span>${data.messages} نامەی نوێ</span></div>`);
        if (data.invites > 0) items.push(`<div class="preview-item"><i class="fas fa-film" style="color:#FFD700;"></i> <span>${data.invites} بانگهێشت</span></div>`);
        
        if (items.length === 0) return;
        
        previewEl.innerHTML = `<div class="preview-content">${items.join('')}</div>`;
        previewEl.classList.add('show');
        
        setTimeout(() => {
            previewEl.classList.remove('show');
            previewEl.classList.add('collapse');
            setTimeout(() => { previewEl.remove(); previewShown = false; }, 500);
        }, 3000);
        
        previewEl.addEventListener('click', () => {
            previewEl.remove();
            previewShown = false;
            openInbox();
        });
    }

    function updateBadge(id, count) {
        const badge = document.getElementById(id);
        if (!badge) return;
        if (count > 0) {
            const oldCount = parseInt(badge.textContent) || 0;
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = '';
            if (count > oldCount) {
                badge.style.animation = 'none';
                requestAnimationFrame(() => { badge.style.animation = ''; });
                const btn = badge.closest('.smart-hub-btn, .mobile-bar-item');
                if (btn) { btn.classList.add('notif-shake'); setTimeout(() => btn.classList.remove('notif-shake'), 1000); }
            }
        } else {
            badge.style.display = 'none';
        }
    }

    if (document.querySelector('.smart-hub') || document.querySelector('.mobile-bottom-bar')) {
        updateSmartHub();
        setInterval(updateSmartHub, 5000);
    }

    // ============ INBOX OVERLAY (TikTok Style - Unified Feed) ============
    const inboxOverlay = document.getElementById('inbox-overlay');
    const inboxCloseBtn = document.getElementById('inbox-overlay-close');
    const hubNotifBtn = document.getElementById('hub-notif-btn');
    const hubFollowersBtn = document.getElementById('hub-followers-btn');
    const mobileInboxBtn = document.getElementById('mobile-inbox-btn');
    const inboxFeed = document.getElementById('inbox-feed');
    let inboxData = { notifications: [], pending: [], recent: [], invites: [], conversations: [] };
    let currentInboxCat = 'all';

    function openInbox() {
        if (!inboxOverlay) return;
        inboxOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        loadInboxFeed();
        CineSound.click();
    }

    function closeInbox() {
        if (!inboxOverlay) return;
        inboxOverlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (hubNotifBtn) hubNotifBtn.addEventListener('click', openInbox);
    if (hubFollowersBtn) hubFollowersBtn.addEventListener('click', () => { openInbox(); setTimeout(() => { const fTab = document.querySelector('[data-inbox-cat="followers"]'); if (fTab) fTab.click(); }, 300); });
    if (mobileInboxBtn) mobileInboxBtn.addEventListener('click', openInbox);
    if (inboxCloseBtn) inboxCloseBtn.addEventListener('click', closeInbox);

    if (inboxOverlay) {
        inboxOverlay.addEventListener('click', (e) => { if (e.target === inboxOverlay) closeInbox(); });
    }

    document.querySelectorAll('.inbox-cat-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.inbox-cat-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentInboxCat = this.dataset.inboxCat;
            renderInboxFeed();
        });
    });

    function loadInboxFeed() {
        if (!inboxFeed) return;
        inboxFeed.innerHTML = '<div class="inbox-loading"><i class="fas fa-spinner fa-spin"></i></div>';

        Promise.all([
            fetch(`${window.SITE_URL}/api/inbox.php?tab=notifications`, { credentials: 'same-origin' }).then(r => r.json()),
            fetch(`${window.SITE_URL}/api/inbox.php?tab=follows`, { credentials: 'same-origin' }).then(r => r.json()),
            fetch(`${window.SITE_URL}/api/inbox.php?tab=invites`, { credentials: 'same-origin' }).then(r => r.json()),
            fetch(`${window.SITE_URL}/api/messages.php?action=conversations`, { credentials: 'same-origin' }).then(r => r.json()).catch(() => ({success:false}))
        ]).then(([notifData, followData, inviteData, msgData]) => {
            inboxData.notifications = notifData.success ? (notifData.items || []) : [];
            inboxData.pending = followData.success ? (followData.pending || []) : [];
            inboxData.recent = followData.success ? (followData.recent || []) : [];
            inboxData.invites = inviteData.success ? (inviteData.invites || []) : [];
            inboxData.conversations = (msgData.success && msgData.conversations) ? msgData.conversations : [];
            renderInboxFeed();
        }).catch(() => {
            inboxFeed.innerHTML = '<div class="inbox-empty"><i class="fas fa-wifi-slash"></i><p>هەڵەیەک ڕوویدا</p></div>';
        });
    }

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
        if (diff < 60) return 'ئێستا';
        if (diff < 3600) return Math.floor(diff / 60) + 'خ';
        if (diff < 86400) return Math.floor(diff / 3600) + 'ک';
        if (diff < 604800) return Math.floor(diff / 86400) + 'ڕ';
        return Math.floor(diff / 604800) + 'ه';
    }

    function renderInboxFeed() {
        if (!inboxFeed) return;
        let html = '';
        const cat = currentInboxCat;

        // Messages tab
        if (cat === 'messages') {
            if (inboxData.conversations.length === 0) {
                html = '<div class="inbox-empty"><i class="fas fa-comments"></i><p>هیچ نامەیەک نییە</p></div>';
            } else {
                inboxData.conversations.forEach(c => {
                    const isOnline = c.is_online;
                    html += `
                        <div class="inbox-feed-item" onclick="window.location='${window.SITE_URL}/messages.php?with=${c.user_id}'">
                            <div class="inbox-avatar">
                                <div class="inbox-avatar-icon" style="background:rgba(37,244,238,0.1);color:#25F4EE;"><i class="fas fa-user"></i></div>
                                ${isOnline ? '<span class="inbox-online-indicator"></span>' : ''}
                            </div>
                            <div class="inbox-feed-content">
                                <div class="inbox-feed-title">${c.username}</div>
                                <div class="inbox-feed-desc">${c.last_message ? c.last_message.substring(0, 40) : ''}</div>
                            </div>
                            <div class="inbox-feed-meta">
                                ${c.unread_count > 0 ? `<span class="inbox-feed-badge">${c.unread_count}</span>` : ''}
                                <div class="inbox-feed-time">${timeAgo(c.last_message_time)}</div>
                            </div>
                        </div>`;
                });
            }
            inboxFeed.innerHTML = html;
            return;
        }

        let items = [];

        // Followers
        if (cat === 'all' || cat === 'followers') {
            const totalFollowers = inboxData.pending.length + inboxData.recent.length;
            if (totalFollowers > 0 && cat === 'all') {
                const lastFollower = inboxData.recent[0] || inboxData.pending[0];
                html += `
                    <div class="inbox-feed-item inbox-grouped-item" onclick="document.querySelector('[data-inbox-cat=followers]').click()">
                        <div class="inbox-avatar"><div class="inbox-avatar-icon followers"><i class="fas fa-user-plus"></i></div></div>
                        <div class="inbox-feed-content">
                            <div class="inbox-feed-title" style="color:#FE2C55;font-weight:700;">فۆڵۆوەرە نوێیەکان</div>
                            <div class="inbox-feed-desc">${lastFollower.username} ${totalFollowers > 1 ? `و ${totalFollowers - 1} کەسی تر` : 'فۆڵۆی تۆی کردووە'}</div>
                        </div>
                        <div class="inbox-feed-meta">
                            ${totalFollowers > 0 ? `<span class="inbox-feed-badge">${totalFollowers}</span>` : ''}
                            <div class="inbox-feed-time">${timeAgo(lastFollower.created_at)}</div>
                        </div>
                    </div>`;
            }

            if (cat === 'followers') {
                if (inboxData.pending.length > 0) {
                    items.push({ type: 'section', label: 'داواکاری فۆڵۆ', icon: 'fa-user-clock' });
                    inboxData.pending.forEach(u => items.push({ type: 'follow-request', data: u }));
                }
                if (inboxData.recent.length > 0) {
                    items.push({ type: 'section', label: 'فۆڵۆوەرە نوێیەکان', icon: 'fa-user-plus' });
                    inboxData.recent.forEach(u => items.push({ type: 'new-follower', data: u }));
                }
                // Mark as read button
                if (inboxData.pending.length > 0 || inboxData.recent.length > 0) {
                    html += `<div style="padding:10px 18px;text-align:center;">
                        <button class="btn btn-glass btn-sm" id="mark-followers-read" onclick="markFollowersRead(this)">
                            <i class="fas fa-check-double"></i> هەموو بخوێنەرەوە
                        </button>
                    </div>`;
                }
            }
        }

        // Invites
        if (cat === 'all' || cat === 'invites') {
            if (inboxData.invites.length > 0) {
                if (cat === 'all') {
                    const lastInvite = inboxData.invites[0];
                    html += `
                        <div class="inbox-feed-item inbox-grouped-item" onclick="document.querySelector('[data-inbox-cat=invites]').click()">
                            <div class="inbox-avatar"><div class="inbox-avatar-icon invites"><i class="fas fa-play-circle"></i></div></div>
                            <div class="inbox-feed-content">
                                <div class="inbox-feed-title" style="color:#25F4EE;font-weight:700;">بانگهێشتی Watch Party</div>
                                <div class="inbox-feed-desc">${lastInvite.inviter_name} بانگهێشتت کردووە بۆ "${lastInvite.movie_title}"</div>
                            </div>
                            <div class="inbox-feed-meta">
                                <span class="inbox-feed-badge">${inboxData.invites.length}</span>
                                <div class="inbox-feed-time">${timeAgo(lastInvite.created_at)}</div>
                            </div>
                        </div>`;
                } else {
                    inboxData.invites.forEach(inv => items.push({ type: 'invite', data: inv }));
                }
            }
        }

        // Messages preview in "all" tab
        if (cat === 'all' && inboxData.conversations.length > 0) {
            const unreadConvos = inboxData.conversations.filter(c => c.unread_count > 0);
            const totalUnread = unreadConvos.reduce((sum, c) => sum + parseInt(c.unread_count || 0), 0);
            const lastConvo = inboxData.conversations[0];
            html += `
                <div class="inbox-feed-item inbox-grouped-item" onclick="document.querySelector('[data-inbox-cat=messages]').click()">
                    <div class="inbox-avatar"><div class="inbox-avatar-icon" style="background:rgba(96,165,250,0.1);color:#60A5FA;"><i class="fas fa-envelope"></i></div></div>
                    <div class="inbox-feed-content">
                        <div class="inbox-feed-title" style="color:#60A5FA;font-weight:700;">نامەکان</div>
                        <div class="inbox-feed-desc">${lastConvo.username}: ${lastConvo.last_message ? lastConvo.last_message.substring(0, 30) : ''}</div>
                    </div>
                    <div class="inbox-feed-meta">
                        ${totalUnread > 0 ? `<span class="inbox-feed-badge">${totalUnread}</span>` : ''}
                        <div class="inbox-feed-time">${timeAgo(lastConvo.last_message_time)}</div>
                    </div>
                </div>`;
        }

        // Notifications in "all" tab
        if (cat === 'all') {
            const activityNotifs = inboxData.notifications.filter(n => !n.title?.includes('سیستەم') && !n.title?.includes('VIP'));
            activityNotifs.forEach(n => {
                html += `
                    <div class="inbox-feed-item" ${n.link ? `onclick="window.location='${n.link}'"` : ''}>
                        <div class="inbox-avatar">
                            ${n.avatar ? `<img src="${window.SITE_URL}/uploads/${n.avatar}" alt="">` : `<div class="inbox-avatar-icon activity"><i class="fas fa-heart"></i></div>`}
                        </div>
                        <div class="inbox-feed-content">
                            <div class="inbox-feed-title">${n.title || ''}</div>
                            <div class="inbox-feed-desc">${n.message || ''}</div>
                        </div>
                        <div class="inbox-feed-time">${timeAgo(n.created_at)}</div>
                    </div>`;
            });
        }

        // Render detailed items
        if (items.length > 0) {
            items.forEach(item => {
                if (item.type === 'section') {
                    html += `<div class="inbox-section-header"><i class="fas ${item.icon}"></i> ${item.label}</div>`;
                    return;
                }
                const d = item.data;

                if (item.type === 'follow-request') {
                    html += `
                        <div class="inbox-feed-item" id="follow-req-${d.id}">
                            <div class="inbox-avatar">
                                ${d.avatar ? `<img src="${window.SITE_URL}/uploads/${d.avatar}" alt="">` : `<div class="inbox-avatar-icon followers"><i class="fas fa-user"></i></div>`}
                            </div>
                            <div class="inbox-feed-content">
                                <div class="inbox-feed-title"><a href="${window.SITE_URL}/profile.php?id=${d.id}" onclick="event.stopPropagation();" style="color:#fff;">${d.username}</a></div>
                                <div class="inbox-feed-desc">داوای فۆڵۆکردنی تۆی کردووە</div>
                                <div class="inbox-feed-actions">
                                    <button class="inbox-action-btn accept" onclick="event.stopPropagation();inboxFollowAction('accept',${d.id},this)"><i class="fas fa-check"></i> قبوڵ</button>
                                    <button class="inbox-action-btn decline" onclick="event.stopPropagation();inboxFollowAction('reject',${d.id},this)"><i class="fas fa-times"></i> ڕەت</button>
                                </div>
                            </div>
                            <div class="inbox-feed-time">${timeAgo(d.created_at)}</div>
                        </div>`;
                }

                if (item.type === 'new-follower') {
                    html += `
                        <div class="inbox-feed-item" onclick="window.location='${window.SITE_URL}/profile.php?id=${d.id}'">
                            <div class="inbox-avatar">
                                ${d.avatar ? `<img src="${window.SITE_URL}/uploads/${d.avatar}" alt="">` : `<div class="inbox-avatar-icon followers"><i class="fas fa-user"></i></div>`}
                            </div>
                            <div class="inbox-feed-content">
                                <div class="inbox-feed-title">${d.username}</div>
                                <div class="inbox-feed-desc">فۆڵۆی تۆی کردووە</div>
                            </div>
                            <div class="inbox-feed-time">${timeAgo(d.created_at)}</div>
                        </div>`;
                }

                if (item.type === 'invite') {
                    html += `
                        <div class="inbox-feed-item inbox-invite-card" id="invite-${d.id}">
                            <div class="inbox-avatar"><div class="inbox-avatar-icon invites"><i class="fas fa-play-circle"></i></div></div>
                            <div class="inbox-feed-content">
                                <div class="inbox-feed-title"><a href="${window.SITE_URL}/profile.php?id=${d.inviter_id}" onclick="event.stopPropagation();" style="color:#fff;">${d.inviter_name}</a></div>
                                <div class="inbox-feed-desc">بانگهێشتت کردووە بۆ "<strong class="text-gold">${d.movie_title}</strong>"</div>
                                <div class="inbox-feed-actions">
                                    <button class="inbox-action-btn accept" onclick="event.stopPropagation();inboxInviteAction('accept',${d.id},'${d.room_id}',this)"><i class="fas fa-play"></i> بەشداری بکە</button>
                                    <button class="inbox-action-btn decline" onclick="event.stopPropagation();inboxInviteAction('decline',${d.id},'${d.room_id}',this)"><i class="fas fa-times"></i> ڕەت</button>
                                </div>
                            </div>
                            <div class="inbox-feed-time">${timeAgo(d.created_at)}</div>
                        </div>`;
                }
            });
        }

        if (!html) {
            html = '<div class="inbox-empty"><i class="fas fa-bell-slash"></i><p>هیچ ئاگادارکردنەوەیەک نییە</p></div>';
        }

        inboxFeed.innerHTML = html;
    }

    // Mark followers as read - actually persists on server
    window.markFollowersRead = function(btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch(`${window.SITE_URL}/api/follow.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({action: 'mark_followers_seen'})
        }).then(r => r.json()).then(d => {
            if (d.success) {
                lastHubCounts.followers = 0;
                updateBadge('hub-followers-badge', 0);
                // Also update mobile bar badge
                const mobileBadge = document.querySelector('.mobile-bar-badge');
                if (mobileBadge) {
                    const total = (lastHubCounts.notifications || 0) + (lastHubCounts.invites || 0);
                    if (total <= 0) mobileBadge.style.display = 'none';
                }
                btn.innerHTML = '<i class="fas fa-check"></i> خوێندرایەوە';
                btn.style.background = 'rgba(74,222,128,0.1)';
                btn.style.borderColor = 'rgba(74,222,128,0.3)';
                btn.style.color = '#4ade80';
                if (typeof CineSound !== 'undefined') CineSound.success();
                // Force refresh counts from server immediately
                updateSmartHub();
                // Reload inbox followers section after a brief delay
                setTimeout(() => {
                    updateSmartHub();
                    // Re-render the followers tab to show updated state
                    const followersTab = document.querySelector('.inbox-cat-tab[data-category="followers"]');
                    if (followersTab && followersTab.classList.contains('active')) {
                        followersTab.click();
                    }
                }, 800);
            }
        }).catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-double"></i> هەموو بخوێنەرەوە';
        });
    };

    // AJAX follow actions in inbox
    window.inboxFollowAction = function(action, followerId, btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        fetch(`${window.SITE_URL}/api/follow.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: action, follower_id: followerId })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                const actionsDiv = btn.closest('.inbox-feed-actions');
                if (action === 'accept') {
                    actionsDiv.innerHTML = '<span class="inbox-action-done"><i class="fas fa-check-circle"></i> قبوڵ کرا</span>';
                    CineSound.success();
                } else {
                    actionsDiv.innerHTML = '<span class="inbox-action-done" style="color:var(--gray);"><i class="fas fa-times-circle"></i> ڕەت کرایەوە</span>';
                }
                // Update badge count
                updateSmartHub();
            }
        });
    };

    // AJAX invite actions in inbox
    window.inboxInviteAction = function(action, inviteId, roomId, btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        fetch(`${window.SITE_URL}/api/invite.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: action, invite_id: inviteId })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                const actionsDiv = btn.closest('.inbox-feed-actions');
                if (action === 'accept') {
                    actionsDiv.innerHTML = '<span class="inbox-action-done"><i class="fas fa-check-circle"></i> بەشداری کرا</span>';
                    CineSound.success();
                    // Redirect to room directly
                    const targetRoom = data.room_id || roomId;
                    if (targetRoom) {
                        setTimeout(() => {
                            window.location.href = `${window.SITE_URL}/room.php?id=${targetRoom}`;
                        }, 800);
                    }
                } else {
                    actionsDiv.innerHTML = '<span class="inbox-action-done" style="color:var(--gray);"><i class="fas fa-times-circle"></i> ڕەت کرا</span>';
                }
                updateSmartHub();
            }
        });
    };

    // ============ GLOBAL USER SEARCH MODAL ============
    const userSearchModal = document.getElementById('user-search-modal');
    const globalSearchBtn = document.getElementById('global-user-search-btn');
    const mobileSearchBtn = document.getElementById('mobile-user-search-btn');
    const userSearchInput = document.getElementById('global-user-search');
    const userSearchResults = document.getElementById('global-user-results');
    let userSearchTimeout;

    function openUserSearch() {
        if (!userSearchModal) return;
        userSearchModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => { if (userSearchInput) userSearchInput.focus(); }, 300);
    }

    function closeUserSearch() {
        if (!userSearchModal) return;
        userSearchModal.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (globalSearchBtn) globalSearchBtn.addEventListener('click', openUserSearch);
    if (mobileSearchBtn) mobileSearchBtn.addEventListener('click', openUserSearch);
    
    if (userSearchModal) {
        userSearchModal.querySelector('.user-search-modal-overlay')?.addEventListener('click', closeUserSearch);
        userSearchModal.querySelector('.user-search-modal-close')?.addEventListener('click', closeUserSearch);
    }

    if (userSearchInput) {
        userSearchInput.addEventListener('input', function() {
            clearTimeout(userSearchTimeout);
            const q = this.value.trim();
            if (q.length < 2) {
                userSearchResults.innerHTML = '<p class="text-gray" style="text-align:center;padding:30px;">ناوی بەکارهێنەر بنووسە بۆ گەڕان</p>';
                return;
            }
            userSearchTimeout = setTimeout(() => {
                fetch(`${window.SITE_URL}/api/user-search.php?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.results || data.results.length === 0) {
                            userSearchResults.innerHTML = '<div class="inbox-empty"><i class="fas fa-search"></i><p>هیچ بەکارهێنەرێک نەدۆزرایەوە</p></div>';
                            return;
                        }
                        userSearchResults.innerHTML = data.results.map(u => `
                            <div class="user-search-result-item">
                                <a href="${window.SITE_URL}/profile.php?id=${u.id}">
                                    <span class="dm-status-dot-inline ${u.is_online ? 'online' : 'offline'}"></span>
                                    <span>${u.username}</span>
                                </a>
                                <button class="btn btn-gold btn-xs follow-btn" data-user-id="${u.id}"><i class="fas fa-user-plus"></i> فۆڵۆ</button>
                            </div>
                        `).join('');
                    });
            }, 300);
        });
    }

    // ============ AWARD POINTS ON WATCH ============
    (function() {
        const movieIdEl = document.querySelector('[data-movie-id]');
        if (movieIdEl && document.querySelector('.video-wrapper')) {
            const mid = movieIdEl.dataset.movieId;
            if (mid) {
                setTimeout(() => {
                    fetch(`${window.SITE_URL}/api/points.php`, {
                        method: 'POST',
                        headers: {'Content-Type':'application/json'},
                        credentials: 'same-origin',
                        body: JSON.stringify({action:'award', type:'watch', movie_id: parseInt(mid)})
                    }).catch(()=>{});
                }, 5000);
            }
        }
    })();

    // ============ UPDATE LAST SEEN ============
    (function() {
        fetch(`${window.SITE_URL}/api/messages.php`, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({action:'heartbeat'})
        }).catch(()=>{});
    })();

    // ============ MOBILE BOTTOM BAR - ACTIVE STATE ============
    (function() {
        const currentPath = window.location.pathname;
        const bottomBar = document.querySelector('.mobile-bottom-bar');
        if (!bottomBar) return;
        bottomBar.querySelectorAll('.mobile-bar-item').forEach(item => {
            const href = item.getAttribute('href');
            if (!href) return;
            const isHome = href.endsWith('/') || href.endsWith('/index.php');
            const isProfile = href.includes('/profile.php');
            const isMessages = href.includes('/messages.php');
            if ((isHome && (currentPath === '/' || currentPath.endsWith('/index.php'))) ||
                (isProfile && currentPath.includes('/profile.php')) ||
                (isMessages && currentPath.includes('/messages.php'))) {
                item.classList.add('active');
            }
        });
    })();

    // ============ DM TYPING INDICATOR ============
    (function() {
        const dmInput = document.getElementById('dm-input');
        const typingEl = document.getElementById('dm-typing');
        if (!dmInput || !typingEl) return;
        const chatWith = typeof dmChatWith !== 'undefined' ? dmChatWith : 0;
        if (!chatWith) return;
        
        let typingTimeout;
        let lastTypingSent = 0;
        
        dmInput.addEventListener('input', function() {
            const now = Date.now();
            if (now - lastTypingSent < 2000) return;
            lastTypingSent = now;
            fetch(`${window.SITE_URL}/api/messages.php`, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({action:'typing', receiver_id: chatWith})
            }).catch(()=>{});
        });

        // Poll for typing status from other user
        setInterval(() => {
            if (!chatWith) return;
            fetch(`${window.SITE_URL}/api/messages.php?action=typing_status&user_id=${chatWith}`, {credentials:'same-origin'})
                .then(r=>r.json()).then(d => {
                    if (d.success && d.is_typing) {
                        typingEl.style.display = '';
                        clearTimeout(typingTimeout);
                        typingTimeout = setTimeout(() => { typingEl.style.display = 'none'; }, 3000);
                    }
                }).catch(()=>{});
        }, 2000);
    })();

    // ============ FOLLOW REQUESTS (Accept/Reject on Profile page) ============
    document.addEventListener('click', function(e) {
        const acceptBtn = e.target.closest('.follow-accept-btn');
        const rejectBtn = e.target.closest('.follow-reject-btn');
        
        if (acceptBtn) {
            const followerId = parseInt(acceptBtn.dataset.followerId);
            acceptBtn.disabled = true;
            acceptBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            fetch(`${window.SITE_URL}/api/follow.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'accept', follower_id: followerId })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    CineSound.success();
                    acceptBtn.closest('.follow-request-item').remove();
                    updateSmartHub();
                }
            });
        }
        
        if (rejectBtn) {
            const followerId = parseInt(rejectBtn.dataset.followerId);
            rejectBtn.disabled = true;
            fetch(`${window.SITE_URL}/api/follow.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'reject', follower_id: followerId })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    rejectBtn.closest('.follow-request-item').remove();
                    updateSmartHub();
                }
            });
        }
    });

    // ============ RESUME WATCHING ============
    const videoWrapperResume = document.querySelector('.video-wrapper');
    if (videoWrapperResume) {
        const movieSlug = window.location.search.match(/slug=([^&]+)/)?.[1] || window.location.pathname.split('/movie/')[1];
        if (movieSlug) {
            const savedTime = localStorage.getItem(`resume_${movieSlug}`);
            if (savedTime && parseInt(savedTime) > 30) {
                const resumeBar = document.getElementById('resume-bar') || document.createElement('div');
                resumeBar.id = 'resume-bar';
                resumeBar.innerHTML = `<i class="fas fa-redo"></i> لە کاتی ${formatTime(parseInt(savedTime))} بەردەوام بە`;
                resumeBar.style.cssText = 'display:flex;padding:12px 18px;background:rgba(255,215,0,0.06);border:1px solid rgba(255,215,0,0.12);border-radius:0 0 12px 12px;color:#FFD700;font-size:0.85rem;cursor:pointer;align-items:center;gap:8px;transition:all 0.3s ease;';
            }
            window.addEventListener('message', function(e) {
                if (e.data && e.data.type === 'timeupdate' && e.data.currentTime) {
                    localStorage.setItem(`resume_${movieSlug}`, Math.floor(e.data.currentTime));
                }
            });
        }
    }

    // ============ REFRESH FOLLOW COUNTS ============
    window.refreshFollowCounts = function(userId) {
        fetch(`${window.SITE_URL}/api/follow.php?action=status&user_id=${userId}`, { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const fc = document.getElementById('followers-count');
                    const gc = document.getElementById('following-count');
                    if (fc) fc.textContent = data.followers_count;
                    if (gc) gc.textContent = data.following_count;
                }
            });
    };

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return h > 0 ? `${h}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}` : `${m}:${s.toString().padStart(2,'0')}`;
    }

    // ============ DISABLE INSPECT ELEMENT & RIGHT CLICK ============
    document.addEventListener('contextmenu', function(e) { e.preventDefault(); return false; });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'F12' || e.keyCode === 123) { e.preventDefault(); return false; }
        if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.keyCode === 73)) { e.preventDefault(); return false; }
        if (e.ctrlKey && e.shiftKey && (e.key === 'J' || e.key === 'j' || e.keyCode === 74)) { e.preventDefault(); return false; }
        if (e.ctrlKey && (e.key === 'U' || e.key === 'u' || e.keyCode === 85)) { e.preventDefault(); return false; }
        if (e.ctrlKey && e.shiftKey && (e.key === 'C' || e.key === 'c' || e.keyCode === 67)) { e.preventDefault(); return false; }
    });

    // ============ GLOBAL PAGE FADE-IN ANIMATION ============
    document.body.classList.add('page-loaded');

    // ============ ENHANCED SOUND: Tab switching, modal open ============
    document.addEventListener('click', function(e) {
        const tabTarget = e.target.closest('.profile-tab, .inbox-cat-tab, .lang-tab, .season-btn');
        if (tabTarget) CineSound.click();
    });

    // ============ MOBILE BOTTOM BAR ACTIVE STATE ============
    const currentPath = window.location.pathname;
    document.querySelectorAll('.mobile-bar-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && currentPath.includes(href.replace(window.SITE_URL, '').split('?')[0])) {
            item.classList.add('active');
        }
    });

    // ============ INBOX SEARCH TOGGLE ============
    const inboxSearchToggle = document.getElementById('inbox-search-toggle');
    if (inboxSearchToggle) {
        inboxSearchToggle.addEventListener('click', function() {
            closeInbox();
            openUserSearch();
        });
    }

});
