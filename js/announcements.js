// Announcement Management System
class AnnouncementManager {
    constructor() {
        this.announcements = [];
        this.closedAnnouncements = this.getClosedAnnouncements();
        this.draggingElement = null;
        this.offset = { x: 0, y: 0 };
    }

    // Get closed announcement IDs from localStorage
    getClosedAnnouncements() {
        const closed = localStorage.getItem('closedAnnouncements');
        return closed ? JSON.parse(closed) : [];
    }

    // Save closed announcement IDs to localStorage
    saveClosedAnnouncements() {
        localStorage.setItem('closedAnnouncements', JSON.stringify(this.closedAnnouncements));
    }

    // Fetch all announcements
    async fetchAnnouncements() {
        try {
            const response = await fetch('announcement_api.php?action=get_all');
            const result = await response.json();
            if (result.success) {
                this.announcements = result.data;
                this.displayAnnouncements();
            }
        } catch (error) {
            console.error('Failed to fetch announcements:', error);
        }
    }

    // Display announcements
    displayAnnouncements() {
        const container = document.getElementById('announcementContainer');
        if (!container) return;

        container.innerHTML = '';

        this.announcements.forEach((announcement, index) => {
            // Check if announcement was closed
            if (this.closedAnnouncements.includes(announcement.id)) {
                return;
            }

            const modal = document.createElement('div');
            modal.className = 'announcement-modal pixel-announcement';
            modal.id = `announcement-${announcement.id}`;

            // Random positions - spread across screen
            const randomTop = Math.random() * (window.innerHeight - 300) + 20;
            const randomLeft = Math.random() * (window.innerWidth - 400) + 20;

            modal.style.cssText = `
                position: fixed;
                top: ${randomTop}px;
                left: ${randomLeft}px;
                background: linear-gradient(135deg, #ffccff 0%, #ffddff 100%);
                color: #08141f;
                padding: 16px;
                border: 4px solid #b366ff;
                box-shadow: 0 0 20px rgba(179, 102, 255, 0.6), 6px 6px 0px rgba(0, 0, 0, 0.2), inset 0 0 0 2px #ffccff;
                width: 320px;
                z-index: ${1000 + index};
                cursor: move;
                user-select: none;
                font-family: 'Press Start 2P', monospace;
                image-rendering: pixelated;
            `;

            // Title bar
            const header = document.createElement('div');
            header.className = 'announcement-header pixel-header';
            header.style.cssText = `
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 8px;
                padding: 4px 8px;
                border-bottom: 3px solid #e6d9ff;
                cursor: move;
                background: linear-gradient(90deg, #b366ff 0%, #8833ff 100%);
                margin: -16px -16px 8px -16px;
                border-top: 3px solid #b366ff;
            `;

            const titleContent = document.createElement('div');
            titleContent.style.cssText = `
                display: flex;
                align-items: center;
                gap: 6px;
                flex: 1;
            `;

            const heart = document.createElement('span');
            heart.textContent = '🗣️';
            heart.style.cssText = `
                font-size: 14px;
            `;

            const title = document.createElement('div');
            title.textContent = announcement.title;
            title.style.cssText = `
                font-weight: bold;
                font-size: 12px;
                color: #fff;
                font-family: 'Press Start 2P', monospace;
                text-transform: uppercase;
                letter-spacing: 1px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                text-shadow: 2px 2px 0px rgba(0, 0, 0, 0.3);
            `;

            titleContent.appendChild(heart);
            titleContent.appendChild(title);

            const closeBtn = document.createElement('button');
            closeBtn.textContent = '✕';
            closeBtn.style.cssText = `
                background: linear-gradient(180deg, #ff66cc 0%, #ff33aa 100%);
                border: 2px solid #ffccee;
                color: white;
                font-size: 16px;
                cursor: pointer;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                transition: all 0.2s;
                font-family: 'Press Start 2P', monospace;
                box-shadow: 0 0 8px rgba(255, 51, 170, 0.5), 2px 2px 0 rgba(0, 0, 0, 0.3);
                flex-shrink: 0;
            `;

            closeBtn.onmouseover = () => {
                closeBtn.style.transform = 'scale(1.1)';
                closeBtn.style.boxShadow = '0 0 12px rgba(255, 51, 170, 0.7), 3px 3px 0 rgba(0, 0, 0, 0.3)';
            };
            closeBtn.onmouseout = () => {
                closeBtn.style.transform = 'scale(1)';
                closeBtn.style.boxShadow = '0 0 8px rgba(255, 51, 170, 0.5), 2px 2px 0 rgba(0, 0, 0, 0.3)';
            };

            closeBtn.onclick = (e) => {
                e.stopPropagation();
                this.closeAnnouncement(announcement.id, modal);
            };

            header.appendChild(titleContent);
            header.appendChild(closeBtn);

            // Content
            const content = document.createElement('div');
            content.className = 'announcement-content pixel-content';
            content.innerHTML = announcement.content;
            content.style.cssText = `
                font-size: 10px;
                line-height: 1.5;
                margin-bottom: 10px;
                color: #08141f;
                font-family: 'Press Start 2P', monospace;
                background: rgba(0, 0, 0, 0.05);
                padding: 8px;
                text-shadow: 1px 1px 0px rgba(255, 255, 255, 0.5);
            `;

            // Button container
            const buttonContainer = document.createElement('div');
            buttonContainer.style.cssText = `
                display: flex;
                justify-content: flex-end;
            `;

            // OK Button
            const okBtn = document.createElement('button');
            okBtn.textContent = 'OK';
            okBtn.style.cssText = `
                width: 50px;
                padding: 4px;
                background: linear-gradient(180deg, #8833ff 0%, #6611dd 100%);
                border: 2px solid #b366ff;
                color: #ffccff;
                font-family: 'Press Start 2P', monospace;
                font-size: 8px;
                cursor: pointer;
                font-weight: bold;
                transition: all 0.2s;
                box-shadow: 0 3px 0 rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.3);
            `;

            okBtn.onmouseover = () => {
                okBtn.style.transform = 'translateY(-1px)';
                okBtn.style.boxShadow = '0 4px 0 rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.3)';
            };
            okBtn.onmouseout = () => {
                okBtn.style.transform = 'translateY(0)';
                okBtn.style.boxShadow = '0 3px 0 rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.3)';
            };

            okBtn.onclick = (e) => {
                e.stopPropagation();
                // Save to server instead of just localStorage
                this.closeAnnouncementOnServer(announcement.id, modal);
            };

            buttonContainer.appendChild(okBtn);

            modal.appendChild(header);
            modal.appendChild(content);
            modal.appendChild(buttonContainer);
            container.appendChild(modal);

            // Add draggable functionality
            this.makeDraggable(modal, header);
        });
    }

    // Close announcement
    closeAnnouncement(id, element) {
        if (!this.closedAnnouncements.includes(id)) {
            this.closedAnnouncements.push(id);
            this.saveClosedAnnouncements();
        }

        element.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => {
            element.remove();
        }, 300);
    }

    // Close announcement on server (per user)
    async closeAnnouncementOnServer(id, element) {
        try {
            const formData = new FormData();
            formData.append('action', 'close');
            formData.append('announcement_id', id);

            await fetch('announcement_api.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Error closing announcement:', error);
        }

        // Animate close
        element.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => {
            element.remove();
        }, 300);
    }

    // Make element draggable
    makeDraggable(element, header) {
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;

        header.onmousedown = (e) => {
            e.preventDefault();
            pos3 = e.clientX;
            pos4 = e.clientY;

            document.onmouseup = () => {
                document.onmouseup = null;
                document.onmousemove = null;
            };

            document.onmousemove = (e) => {
                e.preventDefault();
                pos1 = pos3 - e.clientX;
                pos2 = pos4 - e.clientY;
                pos3 = e.clientX;
                pos4 = e.clientY;

                element.style.top = (element.offsetTop - pos2) + 'px';
                element.style.left = (element.offsetLeft - pos1) + 'px';
            };
        };
    }

    // Initialize
    init() {
        // Create container
        let container = document.getElementById('announcementContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'announcementContainer';
            document.body.appendChild(container);
        }

        // Add styles if not already added
        if (!document.getElementById('announcementStyles')) {
            const style = document.createElement('style');
            style.id = 'announcementStyles';
            style.textContent = `
                @keyframes fadeOut {
                    from {
                        opacity: 1;
                        transform: scale(1);
                    }
                    to {
                        opacity: 0;
                        transform: scale(0.95);
                    }
                }

                .pixel-announcement {
                    font-family: 'Press Start 2P', monospace;
                    image-rendering: pixelated;
                }

                .pixel-announcement h4,
                .pixel-announcement h3,
                .pixel-announcement p {
                    font-family: 'Press Start 2P', monospace;
                    margin: 0;
                    image-rendering: pixelated;
                }

                .pixel-announcement button {
                    font-family: 'Press Start 2P', monospace;
                }

                .pixel-header {
                    font-family: 'Press Start 2P', monospace;
                }

                .pixel-content {
                    font-family: 'Press Start 2P', monospace;
                }

                .pixel-announcement:hover {
                    box-shadow: 0 0 20px rgba(184, 151, 255, 0.5), 5px 5px 0px rgba(58, 42, 82, 0.9) !important;
                }
            `;
            document.head.appendChild(style);
        }

        // Load announcements
        this.fetchAnnouncements();

        // Check for new announcements every 30 seconds
        setInterval(() => {
            this.fetchAnnouncements();
        }, 30000);
    }

    // Reset closed announcements (for testing)
    resetClosedAnnouncements() {
        this.closedAnnouncements = [];
        this.saveClosedAnnouncements();
        this.displayAnnouncements();
    }
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.announcementManager = new AnnouncementManager();
        window.announcementManager.init();
    });
} else {
    window.announcementManager = new AnnouncementManager();
    window.announcementManager.init();
}
