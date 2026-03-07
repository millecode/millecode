document.addEventListener("DOMContentLoaded", () => {
    "use strict";

    const millecodeBody = document.body;
    const millecodeNavbar = document.querySelector("[data-millecode-navbar]");
    const millecodeDrawer = document.querySelector("[data-millecode-drawer]");
    const millecodeDrawerBackdrop = document.querySelector(
        "[data-millecode-drawer-backdrop]",
    );
    const millecodeDrawerOpenButtons = document.querySelectorAll(
        "[data-millecode-drawer-open]",
    );
    const millecodeDrawerCloseButtons = document.querySelectorAll(
        "[data-millecode-drawer-close]",
    );
    const millecodeDrawerLinks = document.querySelectorAll(
        "[data-millecode-drawer-link]",
    );

    const millecodeOpenDrawer = () => {
        if (!millecodeDrawer) return;

        millecodeBody.classList.add("millecode-nav-open");
        millecodeDrawer.setAttribute("aria-hidden", "false");
        millecodeDrawerOpenButtons.forEach((button) => {
            button.setAttribute("aria-expanded", "true");
        });
    };

    const millecodeCloseDrawer = () => {
        if (!millecodeDrawer) return;

        millecodeBody.classList.remove("millecode-nav-open");
        millecodeDrawer.setAttribute("aria-hidden", "true");
        millecodeDrawerOpenButtons.forEach((button) => {
            button.setAttribute("aria-expanded", "false");
        });
    };

    millecodeDrawerOpenButtons.forEach((button) => {
        button.addEventListener("click", millecodeOpenDrawer);
    });

    millecodeDrawerCloseButtons.forEach((button) => {
        button.addEventListener("click", millecodeCloseDrawer);
    });

    if (millecodeDrawerBackdrop) {
        millecodeDrawerBackdrop.addEventListener("click", millecodeCloseDrawer);
    }

    millecodeDrawerLinks.forEach((link) => {
        link.addEventListener("click", millecodeCloseDrawer);
    });

    document.addEventListener("keydown", (event) => {
        if (
            event.key === "Escape" &&
            millecodeBody.classList.contains("millecode-nav-open")
        ) {
            millecodeCloseDrawer();
        }
    });

    const millecodeHandleNavbarState = () => {
        if (!millecodeNavbar) return;

        if (window.scrollY > 24) {
            millecodeNavbar.classList.add("millecode-is-scrolled");
        } else {
            millecodeNavbar.classList.remove("millecode-is-scrolled");
        }
    };

    millecodeHandleNavbarState();
    window.addEventListener("scroll", millecodeHandleNavbarState, {
        passive: true,
    });

    const millecodeAnchorLinks = document.querySelectorAll('a[href^="#"]');

    millecodeAnchorLinks.forEach((link) => {
        link.addEventListener("click", (event) => {
            const millecodeTargetSelector = link.getAttribute("href");

            if (!millecodeTargetSelector || millecodeTargetSelector === "#") {
                return;
            }

            const millecodeTarget = document.querySelector(
                millecodeTargetSelector,
            );

            if (!millecodeTarget) {
                return;
            }

            event.preventDefault();

            const millecodeNavbarHeight = millecodeNavbar
                ? millecodeNavbar.offsetHeight
                : 0;
            const millecodeTargetTop =
                millecodeTarget.getBoundingClientRect().top +
                window.pageYOffset;
            const millecodeOffset =
                millecodeTargetTop - millecodeNavbarHeight - 12;

            window.scrollTo({
                top: millecodeOffset,
                behavior: "smooth",
            });
        });
    });

    const millecodeRevealItems = document.querySelectorAll(
        "[data-millecode-reveal]",
    );

    if ("IntersectionObserver" in window && millecodeRevealItems.length > 0) {
        const millecodeRevealObserver = new IntersectionObserver(
            (entries, observer) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("millecode-is-visible");
                        observer.unobserve(entry.target);
                    }
                });
            },
            {
                threshold: 0.14,
                rootMargin: "0px 0px -40px 0px",
            },
        );

        millecodeRevealItems.forEach((item) => {
            millecodeRevealObserver.observe(item);
        });
    } else {
        millecodeRevealItems.forEach((item) => {
            item.classList.add("millecode-is-visible");
        });
    }

    const millecodeCarousel = document.querySelector(
        "[data-millecode-carousel]",
    );

    if (millecodeCarousel) {
        const millecodeTrack = millecodeCarousel.querySelector(
            "[data-millecode-carousel-track]",
        );
        const millecodeItems = millecodeCarousel.querySelectorAll(
            "[data-millecode-carousel-item]",
        );
        const millecodePrevButton = millecodeCarousel.querySelector(
            "[data-millecode-carousel-prev]",
        );
        const millecodeNextButton = millecodeCarousel.querySelector(
            "[data-millecode-carousel-next]",
        );
        const millecodeViewport = millecodeCarousel.querySelector(
            ".millecode-testimonials-viewport",
        );

        let millecodeCurrentIndex = 0;
        let millecodeInterval = null;

        const millecodeGetGap = () => {
            if (!millecodeTrack) return 24;

            const millecodeTrackStyles =
                window.getComputedStyle(millecodeTrack);
            const millecodeGap = parseFloat(
                millecodeTrackStyles.columnGap ||
                    millecodeTrackStyles.gap ||
                    "24",
            );

            return Number.isNaN(millecodeGap) ? 24 : millecodeGap;
        };

        const millecodeGetItemsPerView = () => {
            if (window.innerWidth < 768) return 1;
            if (window.innerWidth < 1200) return 2;
            return 3;
        };

        const millecodeUpdateCarousel = () => {
            if (
                !millecodeTrack ||
                millecodeItems.length === 0 ||
                !millecodeViewport
            )
                return;

            const millecodeItemsPerView = millecodeGetItemsPerView();
            const millecodeMaxIndex = Math.max(
                millecodeItems.length - millecodeItemsPerView,
                0,
            );

            if (millecodeCurrentIndex > millecodeMaxIndex) {
                millecodeCurrentIndex = 0;
            }

            const millecodeFirstItem = millecodeItems[0];
            const millecodeGap = millecodeGetGap();
            const millecodeStep =
                millecodeFirstItem.getBoundingClientRect().width + millecodeGap;
            const millecodeTranslateX = millecodeCurrentIndex * millecodeStep;

            millecodeTrack.style.transform = `translateX(-${millecodeTranslateX}px)`;
        };

        const millecodeGoToNext = () => {
            const millecodeItemsPerView = millecodeGetItemsPerView();
            const millecodeMaxIndex = Math.max(
                millecodeItems.length - millecodeItemsPerView,
                0,
            );

            millecodeCurrentIndex =
                millecodeCurrentIndex >= millecodeMaxIndex
                    ? 0
                    : millecodeCurrentIndex + 1;
            millecodeUpdateCarousel();
        };

        const millecodeGoToPrev = () => {
            const millecodeItemsPerView = millecodeGetItemsPerView();
            const millecodeMaxIndex = Math.max(
                millecodeItems.length - millecodeItemsPerView,
                0,
            );

            millecodeCurrentIndex =
                millecodeCurrentIndex <= 0
                    ? millecodeMaxIndex
                    : millecodeCurrentIndex - 1;
            millecodeUpdateCarousel();
        };

        const millecodeStartAutoplay = () => {
            millecodeStopAutoplay();
            millecodeInterval = window.setInterval(millecodeGoToNext, 4800);
        };

        const millecodeStopAutoplay = () => {
            if (millecodeInterval) {
                window.clearInterval(millecodeInterval);
                millecodeInterval = null;
            }
        };

        if (millecodeNextButton) {
            millecodeNextButton.addEventListener("click", () => {
                millecodeGoToNext();
                millecodeStartAutoplay();
            });
        }

        if (millecodePrevButton) {
            millecodePrevButton.addEventListener("click", () => {
                millecodeGoToPrev();
                millecodeStartAutoplay();
            });
        }

        millecodeCarousel.addEventListener("mouseenter", millecodeStopAutoplay);
        millecodeCarousel.addEventListener(
            "mouseleave",
            millecodeStartAutoplay,
        );
        millecodeCarousel.addEventListener("focusin", millecodeStopAutoplay);
        millecodeCarousel.addEventListener("focusout", millecodeStartAutoplay);

        window.addEventListener("resize", millecodeUpdateCarousel);

        millecodeUpdateCarousel();
        millecodeStartAutoplay();
    }
});

document.addEventListener("DOMContentLoaded", () => {
    const millecodeContactModal = document.querySelector(
        '[data-millecode-contact-modal="true"]',
    );

    if (
        millecodeContactModal &&
        millecodeContactModal.dataset.millecodeContactModalAutoshow ===
            "true" &&
        typeof bootstrap !== "undefined"
    ) {
        const millecodeBsModal = new bootstrap.Modal(millecodeContactModal);
        millecodeBsModal.show();
    }
});
