import { Controller } from "@hotwired/stimulus";

const colors = {
    vert: "#88cc9d",
    verte: "#88cc9d",
    bleu: "#83aff1",
    bleue: "#83aff1",
    jaune: "#e4ce7b",
    orange: "#eaa56f",
    rouge: "#f17e8d",
    violet: "#b49be3",
    violette: "#b49be3",
    noir: "#aaa4b0",
    noire: "#aaa4b0",
    blanc: "#f0eaee",
    blanche: "#f0eaee",
    rose: "#e8a3cc",
};

export default class extends Controller {
    static targets = [
        "grid",
        "card",
        "empty",
        "status",
        "total",
        "successes",
        "sectors",
        "count",
        "clock",
        "date",
        "sector",
        "color",
        "gradeMin",
        "gradeMax",
        "pagination",
        "page",
        "pause",
        "fullscreen",
    ];
    static values = { url: String };

    connect() {
        this.blocks = [];
        this.pageIndex = 0;
        this.paused = false;
        this.received = false;
        this.filters = new URLSearchParams();
        this.clockFormatter = new Intl.DateTimeFormat("fr-FR", {
            hour: "2-digit",
            minute: "2-digit",
            timeZone: "Europe/Paris",
        });
        this.updateClock();
        this.clockTimer = setInterval(() => this.updateClock(), 1000);
        this.refreshTimer = setInterval(() => this.load(), 30000);
        this.pageTimer = setInterval(() => {
            if (!this.paused && this.pageCount > 1) this.next();
        }, 12000);
        this.onResize = () => this.render();
        window.addEventListener("resize", this.onResize);
        this.load(true);
    }

    disconnect() {
        clearInterval(this.clockTimer);
        clearInterval(this.refreshTimer);
        clearInterval(this.pageTimer);
        this.request?.abort();
        window.removeEventListener("resize", this.onResize);
    }

    updateClock() {
        const now = new Date();
        this.clockTarget.textContent = this.clockFormatter.format(now);
        this.clockTarget.dateTime = now.toISOString();
        this.dateTarget.textContent = new Intl.DateTimeFormat("fr-FR", {
            weekday: "long",
            day: "numeric",
            month: "long",
            timeZone: "Europe/Paris",
        }).format(now);
    }

    async load(initial = false) {
        this.request?.abort();
        const request = new AbortController();
        this.request = request;
        const timeout = setTimeout(() => request.abort(), 10000);
        this.gridTarget.setAttribute("aria-busy", "true");
        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.search = this.filters.toString();
            const response = await fetch(url, {
                headers: { Accept: "application/json" },
                signal: request.signal,
                cache: "no-store",
            });
            if (!response.ok) throw new Error("API unavailable");
            const payload = await response.json();
            if (!Array.isArray(payload.blocs))
                throw new Error("Invalid response");
            if (this.request !== request || !this.element.isConnected) return;
            this.blocks = payload.blocs;
            this.received = true;
            if (initial || !this.choicesLoaded) {
                this.populateChoices();
                if (initial) {
                    const params = new URLSearchParams(window.location.search);
                    for (const key of ["sector", "color", "gradeMin", "gradeMax"]) {
                        const value = params.get(key);
                        if (value) {
                            this[key + "Target"].value = value;
                            this.filters.set(key, value);
                        }
                    }
                    if (this.filters.size) {
                        this.load();
                        return;
                    }
                }
            }
            this.render();
            this.statusTarget.textContent =
                "Actualisé à " +
                this.clockFormatter.format(new Date()) +
                " · mise à jour toutes les 30 s";
            delete this.statusTarget.dataset.error;
        } catch {
            if (this.request !== request || !this.element.isConnected) return;
            this.statusTarget.textContent = this.received
                ? "Connexion interrompue · dernières données conservées · nouvel essai automatique"
                : "Les blocs ne sont pas disponibles pour le moment · nouvel essai automatique";
            this.statusTarget.dataset.error = "";
        } finally {
            clearTimeout(timeout);
            if (this.request === request)
                this.gridTarget.setAttribute("aria-busy", "false");
        }
    }

    populateChoices() {
        const sectors = new Map();
        const blockColors = new Set();
        for (const block of this.blocks) {
            sectors.set(String(block.sector.id), block.sector.name);
            blockColors.add(block.color);
        }
        this.sectorTarget.replaceChildren(new Option("Tous les secteurs", ""));
        for (const [id, name] of [...sectors].sort((a, b) =>
            a[1].localeCompare(b[1], "fr"),
        )) {
            this.sectorTarget.add(new Option(name, id));
        }
        this.colorTarget.replaceChildren(new Option("Toutes les couleurs", ""));
        for (const color of [...blockColors].sort((a, b) =>
            a.localeCompare(b, "fr"),
        )) {
            this.colorTarget.add(
                new Option(
                    color.charAt(0).toUpperCase() + color.slice(1),
                    color,
                ),
            );
        }
        this.choicesLoaded = true;
    }

    filter(event) {
        event.preventDefault();
        if (!this.validateRange()) {
            this.gradeMaxTarget.reportValidity();
            return;
        }
        this.filters = new URLSearchParams();
        for (const key of ["sector", "color", "gradeMin", "gradeMax"]) {
            const value = this[key + "Target"].value;
            if (value) this.filters.set(key, value);
        }
        this.pageIndex = 0;
        this.updateUrl();
        this.load();
    }

    reset() {
        this.gradeMaxTarget.setCustomValidity("");
        for (const target of [
            this.sectorTarget,
            this.colorTarget,
            this.gradeMinTarget,
            this.gradeMaxTarget,
        ])
            target.value = "";
        this.filters = new URLSearchParams();
        this.pageIndex = 0;
        this.updateUrl();
        this.load();
    }

    validateRange() {
        const min = this.gradeMinTarget.value;
        const max = this.gradeMaxTarget.value;
        const valid = !min || !max || Number(min) <= Number(max);
        this.gradeMaxTarget.setCustomValidity(valid ? "" : "La cotation maximale doit être supérieure ou égale à la cotation minimale.");

        return valid;
    }

    updateUrl() {
        const url = new URL(window.location.href);
        url.search = this.filters.toString();
        window.history.replaceState(null, "", url);
    }

    get pageSize() {
        if (window.innerWidth <= 520) return 4;
        return window.innerWidth <= 1200 && window.innerWidth > 850 ? 6 : 8;
    }

    get pageCount() {
        return Math.max(1, Math.ceil(this.blocks.length / this.pageSize));
    }

    render() {
        this.pageIndex = Math.min(this.pageIndex, this.pageCount - 1);
        const fragment = document.createDocumentFragment();
        for (const block of this.blocks.slice(
            this.pageIndex * this.pageSize,
            (this.pageIndex + 1) * this.pageSize,
        )) {
            const card = this.cardTarget.content.cloneNode(true);
            card.querySelector("article").style.setProperty(
                "--block-color",
                colors[block.color.toLowerCase()] || "#b7b0bf",
            );
            for (const [key, value] of Object.entries({
                name: block.name,
                color: block.color,
                grade: block.gradeLabel,
                sector: block.sector.name,
                successCount: block.successCount,
                successLabel: block.successCount > 1 ? "réussites" : "réussite",
            })) {
                card.querySelector('[data-field="' + key + '"]').textContent =
                    String(value);
            }
            fragment.append(card);
        }
        this.gridTarget.replaceChildren(fragment);
        this.emptyTarget.hidden = this.blocks.length > 0 || !this.received;
        this.totalTarget.textContent = this.blocks.length;
        this.countTarget.textContent = this.blocks.length;
        this.successesTarget.textContent = new Intl.NumberFormat(
            "fr-FR",
        ).format(
            this.blocks.reduce((sum, block) => sum + block.successCount, 0),
        );
        this.sectorsTarget.textContent = new Set(
            this.blocks.map((block) => block.sector.id),
        ).size;
        this.paginationTarget.hidden = this.pageCount <= 1;
        this.pageTarget.textContent =
            "PAGE " + (this.pageIndex + 1) + " / " + this.pageCount;
    }

    next() {
        this.pageIndex = (this.pageIndex + 1) % this.pageCount;
        this.render();
    }

    previous() {
        this.pageIndex = (this.pageIndex - 1 + this.pageCount) % this.pageCount;
        this.render();
    }

    togglePause() {
        this.paused = !this.paused;
        this.pauseTarget.textContent = this.paused
            ? "Reprendre le défilement"
            : "Mettre en pause";
        this.pauseTarget.setAttribute("aria-pressed", String(this.paused));
    }

    async fullscreen() {
        try {
            if (document.fullscreenElement) await document.exitFullscreen();
            else await this.element.requestFullscreen();
        } catch {
            this.fullscreenTarget.textContent = "Plein écran indisponible";
        }
    }
}
