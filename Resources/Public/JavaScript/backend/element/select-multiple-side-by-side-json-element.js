import { html, LitElement } from "lit";
import { repeat } from "lit/directives/repeat.js";
import { lll } from "@typo3/core/lit-helper.js";
import SelectBoxFilter from "@typo3/backend/form-engine/element/extra/select-box-filter.js";

class SelectMultipleSideBySideJsonElement extends LitElement {
    entries = [];
    selectedItem = null;
    static get properties() {
        return {
            possibleItems: { type: Array },
            dataId: { type: String },
            availableOptionsId: { type: String }
        };
    }
    createRenderRoot() {
        return this;
    }
    firstUpdated() {
        new SelectBoxFilter(this.renderRoot.querySelector(`#${this.availableOptionsId}`));
    }
    updateEntries() {
        const data = document.getElementById(this.dataId);

        data.value = JSON.stringify(this.entries);
        data.dispatchEvent(new CustomEvent("change", { bubbles: false }));
        this.requestUpdate();
    }
    render() {
        const data = document.getElementById(this.dataId);
        const jsonEntries = data.value;
        if (jsonEntries) {
            this.entries = JSON.parse(jsonEntries);
        } else {
            this.entries = [];
        }
        return html`
            <div class="formengine-field-item t3js-formengine-field-item">
                <div class="form-control-wrap" style="overflow: auto">
                    <div class="form-wizards-wrap">
                        <div class="form-wizards-element">
                            <div
                                class="form-multigroup-wrap t3js-formengine-field-group"
                            >
                                ${this.renderSelectedItems()}
                                ${this.renderAvailableItems()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    renderSelectedItems() {
        const updateValue = (event) => {
            const value = event.target.value;
            this.selectedItem = value;
            this.requestUpdate();
        };

        return html`
            <div class="form-multigroup-item form-multigroup-element">
                <label>${lll("labels.selected")}</label>
                <div class="form-wizards-wrap form-wizards-aside">
                    <div class="form-wizards-element">
                        <select
                            size="2"
                            class="form-select"
                            multiple
                            @change="${updateValue}"
                        >
                            ${repeat(
                                this.entries,
                                (item) => item,
                                (item) => {
                                    const label = this.possibleItems.find(
                                        (possibleItem) =>
                                            possibleItem.value === item
                                    )?.label;
                                    return html`
                                        <option value="${item}">
                                            ${label}
                                        </option>
                                    `;
                                }
                            )}
                        </select>
                    </div>
                    <div
                        class="form-wizards-items-aside form-wizards-items-aside--move"
                    >
                        ${this.renderButtons()}
                    </div>
                </div>
            </div>
        `;
    }
    renderButtons() {
        const index = this.entries.findIndex(
            (entry) => entry === this.selectedItem
        );
        const entry = this.entries[index];

        const removeValue = () => {
            this.entries.splice(index, 1);
            this.updateEntries();
        };

        const moveUp = () => {
            if (index > 0) {
                this.entries.splice(index, 1);
                this.entries.splice(index - 1, 0, entry);
                this.updateEntries();
            }
        };

        const moveDown = () => {
            if (index < this.entries.length - 1) {
                this.entries.splice(index, 1);
                this.entries.splice(index + 1, 0, entry);
                this.updateEntries();
            }
        };

        return html`
            <div class="btn-group-vertical">
                <button
                    class="btn btn-default"
                    type="button"
                    @click="${moveUp}"
                >
                    <typo3-backend-icon
                        identifier="actions-move-up"
                        size="small"
                    ></typo3-backend-icon>
                </button>
                <button
                    class="btn btn-default"
                    type="button"
                    @click="${moveDown}"
                >
                    <typo3-backend-icon
                        identifier="actions-move-down"
                        size="small"
                    ></typo3-backend-icon>
                </button>
                <button
                    class="btn btn-default"
                    type="button"
                    @click="${removeValue}"
                >
                    <typo3-backend-icon
                        identifier="actions-delete"
                        size="small"
                    ></typo3-backend-icon>
                </button>
            </div>
        `;
    }
    renderAvailableItems() {
        const possibleItems = this.getUnusedItems();
        const updateValue = (event) => {
            const value = event.target.value;
            this.entries.push(value);
            this.updateEntries();
        };

        return html`
            <div class="form-multigroup-item form-multigroup-element">
                <label>${lll("labels.items")}</label>
                <div class="form-wizards-wrap form-wizards-aside">
                    <div class="form-wizards-element">
                        ${this.renderFilter()}
                        <select
                            id="${this.availableOptionsId}"
                            size="2"
                            class="form-select t3js-formengine-select-itemstoselect"
                            multiple
                            @change="${updateValue}"
                        >
                            ${repeat(
                                possibleItems,
                                (item) => item.value,
                                (item) => html`
                                    <option value="${item.value}">
                                        ${item.label}
                                    </option>
                                `
                            )}
                        </select>
                    </div>
                </div>
            </div>
        `;
    }
    renderFilter() {
        return html`
            <div class="form-multigroup-item-wizard">
                <span class="input-group input-group-sm">
                    <span class="input-group-text">
                        <typo3-backend-icon
                            identifier="actions-filter"
                            size="small"
                        ></typo3-backend-icon>
                    </span>
                    <input
                        class="t3js-formengine-multiselect-filter-textfield form-control"
                        value=""
                    />
                </span>
            </div>
        `;
    }
    getUnusedItems() {
        return this.possibleItems.filter(
            (item) => !this.entries.some((entry) => entry == item.value)
        );
    }
}

customElements.define(
    "typo3-backend-select-multiple-side-by-side-json-element",
    SelectMultipleSideBySideJsonElement
);
