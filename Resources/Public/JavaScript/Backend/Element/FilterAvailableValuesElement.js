define(
    ["lit", "lit/directives/repeat", "TYPO3/CMS/Core/lit-helper"],
    (function ({ html, LitElement }, { repeat }, { lll }) {
        class FilterAvailableValuesElement extends LitElement {
            entries = []
            static get properties() {
                return {
                    possibleItems: { type: Array },
                    dataId: { type: String }
                }
            }
            getUnusedItems(currentItem) {
                console.log(this.possibleItems)
                return this.possibleItems.filter((item => {
                    return item.value == currentItem?.value || !this.entries.some((entry) => entry.value == item.value)
                }))
            }
            createRenderRoot() {
                return this
            }
            updateEntries() {
                const data = document.getElementById(this.dataId)
                data.value = JSON.stringify(this.entries)
                data.dispatchEvent(new CustomEvent("change", { bubbles: false }))
                this.requestUpdate()
            }
            render() {
                const data = document.getElementById(this.dataId)
                this.entries = data.value ? JSON.parse(data.value) : []
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
                                    <div>${this.renderAddButton()}</div>
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
                            <div style="height: 0; visibility: hidden;">${this.renderEntryButtons()}</div>
                        </div>
                    `
                }
            }
            renderValue(entry, index) {
                const updateValue = (event) => {
                    const value = event.target.value
                    this.entries[index].value = value
                    this.updateEntries()
                }

                const possibleItems = this.getUnusedItems(entry)

                return html`
                    <select class="form-select" @change="${updateValue}">
                        ${repeat(possibleItems, (item) => item.value, (item) => html`
                            <option ?selected="${item.value == entry.value}" value="${item.value}">${item.label}</option>
                        `)}
                    </select>
                `
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
                    <button class="btn btn-default" type="button" ?disabled="${disabled}" @click="${addEntry}">
                        <typo3-backend-icon identifier="actions-add" size="small"></typo3-backend-icon>
                    </button>
                `;
            }
            renderEntryButtons(index) {
                const removeValue = () => {
                    this.entries.splice(index, 1)
                    this.updateEntries()
                }

                const moveUp = () => {
                    if (index > 0) {
                        const element = this.entries[index]
                        this.entries.splice(index, 1)
                        this.entries.splice(index - 1, 0, element)
                        this.updateEntries()
                    }
                }

                const moveDown = () => {
                    if (index < this.entries.length - 1) {
                        const element = this.entries[index]
                        this.entries.splice(index, 1)
                        this.entries.splice(index + 1, 0, element)
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
        customElements.define('typo3-backend-filter-available-values-element', FilterAvailableValuesElement)
        return FilterAvailableValuesElement
    })
)