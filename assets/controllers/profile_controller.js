import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["tab", "panel", "dialog", "deleteTrigger"];
    static values = { deleteInvalid: Boolean };

    connect() {
        if (this.deleteInvalidValue) this.openDelete();
    }

    select(event) {
        this.activate(this.tabTargets.indexOf(event.currentTarget));
    }

    activate(index) {
        this.tabTargets.forEach((tab, position) => {
            const active = position === index;
            tab.setAttribute("aria-selected", String(active));
            tab.tabIndex = active ? 0 : -1;
            this.panelTargets[position].hidden = !active;
        });
    }

    navigate(event) {
        const index = this.tabTargets.indexOf(event.target);
        if (index < 0) return;
        let next;
        switch (event.key) {
            case "ArrowRight":
                next = (index + 1) % this.tabTargets.length;
                break;
            case "ArrowLeft":
                next =
                    (index + this.tabTargets.length - 1) %
                    this.tabTargets.length;
                break;
            case "Home":
                next = 0;
                break;
            case "End":
                next = this.tabTargets.length - 1;
                break;
            default:
                return;
        }
        event.preventDefault();
        this.activate(next);
        this.tabTargets[next].focus();
    }

    openDelete() {
        if (!this.dialogTarget.open) this.dialogTarget.showModal();
    }

    closeDelete() {
        this.dialogTarget.close();
    }

    restoreFocus() {
        this.deleteTriggerTarget.focus();
    }

    disconnect() {
        if (this.dialogTarget.open) this.dialogTarget.close();
    }
}
