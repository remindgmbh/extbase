import { html, LitElement } from 'lit';
import { repeat } from 'lit/directives/repeat.js';
import { lll } from '@typo3/core/lit-helper.js';
import { default as Modal } from '@typo3/backend/modal.js';

class ValueLabelPairsElement extends LitElement {
    entries = []
    static get properties() {
        return {
            possibleItems: { type: Array },
            dataId: { type: String },
            itemProps: { type: Array },
            customValueEditorUrl: { type: String }
        }
    }
    constructor() {
        super()
        window.addEventListener('message', (e) => {
            const data = e.data
            if (data.dataId === this.dataId) {
                if (!this.entries[data.index]) {
                    this.entries[data.index] = {}
                }

                this.entries[data.index].value = btoa(JSON.stringify(data.value))
                this.updateEntries()
            }
        });
    }
    isCustomValue(entry) {
        return !this.possibleItems.some((item) => item.value === entry.value)
    }
    getUnusedItems(currentItem) {
        return this.possibleItems.filter((item => {
            return item.value == currentItem?.value || !this.entries.some((entry) => entry.value == item.value)
        }))
    }
    createRenderRoot() {
        return this
    }
    updateEntries() {
        const data = document.getElementById(this.dataId)

        const base64Entries = this.entries.map((entry) => {
            return btoa(JSON.stringify({
                label: entry['label'],
                value: JSON.parse(atob(entry['value']))
            }))
        }).join(',')

        data.value = base64Entries
        data.dispatchEvent(new CustomEvent("change", { bubbles: false }))
        this.requestUpdate()
    }
    render() {
        const data = document.getElementById(this.dataId)
        const base64Entries = data.value
        if (base64Entries) {
            this.entries = base64Entries.split(',').map((entry) => {
                const result = JSON.parse(atob(entry))
                result['value'] = btoa(JSON.stringify(result['value']))
                return result
            })
        } else {
            this.entries = []
        }
        return html`
            <div class="formengine-field-item t3js-formengine-field-item">
                <div class="form-control-wrap" style="overflow: auto">
                    <div class="form-wizards-wrap">
                        <div>
                            ${this.renderColumnHeaders()}
                            ${repeat(this.entries, (entry) => entry.value, (entry, index) => html`
                                <div style="display: flex; align-items: center; gap: 5px; padding: 0.5rem 0;">
                                    <div style="width: 50%;">
                                        ${this.renderValue(entry, index)}
                                    </div>
                                    <div style="width: 50%;">
                                        ${this.renderLabel(entry, index)}
                                    </div>
                                    <div>${this.renderEntryButtons(index)}</div>
                                </div>
                            `)}
                            <div style="display: flex; gap: 1rem;">
                                <div>${this.renderAddButton()}</div>
                                <div>${this.renderCustomAddButton()}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `
    }
    renderColumnHeaders() {
        if (this.entries.length > 0) {
            return html`
                <div style="display: flex">
                    <div style="width: 50%;">${lll('value')}</div>
                    <div style="width: 50%;">${lll('label')}</div>
                    <!-- Used to align labels above input fields -->
                    <div style="flex-basis: 124px; flex-grow: 0; flex-shrink: 0;"></div>
                </div>
            `
        }
    }
    renderValue(entry, index) {
        const renderInput = () => {
            const editValue = () => {
                this.showModal(index, entry.value)
            }
            const jsonValue = atob(entry.value)
            const keys = Object.keys(JSON.parse(jsonValue))
            const difference = keys.filter(key => !this.itemProps.includes(key))
            const invalidValue = difference.length > 0
            const value = invalidValue ? lll('labels.noMatchingValue').replace('%s', jsonValue) : jsonValue
            
            return html`
                <div style="display: flex;">
                    <input class="form-control" type="text" value="${value}" disabled>
                    <button class="btn btn-default" type="button" @click="${editValue}" ?disabled="${invalidValue}">
                        <typo3-backend-icon identifier="actions-open" size="small"></typo3-backend-icon>
                    </button>
                </div>
            `
        }

        const renderSelect = () => {
            const possibleItems = this.getUnusedItems(entry)
            const updateValue = (event) => {
                const value = event.target.value
                this.entries[index].value = value
                this.updateEntries()
            }
            return html`
                <select class="form-select" @change="${updateValue}">
                    ${repeat(possibleItems, (item) => item.value, (item) => html`
                        <option ?selected="${item.value == entry.value}" value="${item.value}">${item.label}</option>
                    `)}
                </select>
            `
        }

        return this.isCustomValue(entry) ? renderInput() : renderSelect()
    }
    renderLabel(entry, index) {
        const updateLabel = (event) => {
            const label = event.target.value
            this.entries[index].label = label
            this.updateEntries()
        }
        return html`
            <input class="form-control" type="text" value="${entry.label}" @change="${updateLabel}">
        `
    }
    renderAddButton() {
        const unusedItems = this.getUnusedItems()
        const addEntry = () => {
            const newValue = unusedItems[0].value
            this.entries.push({ label: "", value: newValue })
            this.updateEntries()
        }
        const disabled = unusedItems.length === 0
        return html`
            <button class="btn btn-default" type="button" ?disabled="${disabled}" @click="${addEntry}" title="${lll('addEntry')}">
                <typo3-backend-icon identifier="actions-add" size="small"></typo3-backend-icon>
            </button>
        `;
    }
    renderCustomAddButton() {
        return html`
            <button class="btn btn-default" type="button" @click="${() => this.showModal(this.entries.length)}" title="${lll('addCustomEntry')}">
                <typo3-backend-icon identifier="actions-variable-add" size="small"></typo3-backend-icon>
            </button>
        `;
    }
    showModal(index, value) {
        const itemPropsParam = btoa(JSON.stringify(this.itemProps))
        const valueParam = value ? JSON.stringify(value) : undefined
        Modal.advanced({
            type: Modal.types.iframe,
            content: `${this.customValueEditorUrl}&dataId=${this.dataId}&props=${itemPropsParam}&index=${index}&value=${valueParam}`,
            size: Modal.sizes.medium,
        });
    }
    renderEntryButtons(index) {
        const entry = this.entries[index]

        const removeValue = () => {
            this.entries.splice(index, 1)
            this.updateEntries()
        }

        const moveUp = () => {
            if (index > 0) {
                this.entries.splice(index, 1)
                this.entries.splice(index - 1, 0, entry)
                this.updateEntries()
            }
        }

        const moveDown = () => {
            if (index < this.entries.length - 1) {
                this.entries.splice(index, 1)
                this.entries.splice(index + 1, 0, entry)
                this.updateEntries()
            }
        }

        return html`
            <span class="btn-group">
                <button class="btn btn-default" type="button" @click="${moveUp}">
                    <typo3-backend-icon identifier="actions-chevron-up" size="small"></typo3-backend-icon>
                </button>
                <button class="btn btn-default" type="button" @click="${moveDown}">
                    <typo3-backend-icon identifier="actions-chevron-down" size="small"></typo3-backend-icon>
                </button>
                <button class="btn btn-default" type="button" @click="${removeValue}">
                    <typo3-backend-icon identifier="actions-delete" size="small"></typo3-backend-icon>
                </button>
            </span>
        `
    }
}

customElements.define('typo3-backend-value-label-pairs-element', ValueLabelPairsElement)
