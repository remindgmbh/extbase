define(
    ["lit", "TYPO3/CMS/Backend/Utility/MessageUtility", "lit/directives/repeat"],
    (function ({ html, LitElement }, { MessageUtility }, { repeat }) {
        class FieldValuesEditor extends LitElement {
            updatedValue = {}
            static get properties() {
                return {
                    dataId: { type: String },
                    fields: { type: Array },
                    index: { type: Number },
                    value: { type: Object }
                }
            }
            firstUpdated() {
                this.updatedValue = this.value ? this.value : this.fields.reduce((result, field) => {
                    result[field] = ''
                    return result
                }, {})
                this.requestUpdate()
            }
            createRenderRoot() {
                return this
            }
            render() {
                return html`
                    <div style="padding: 1rem;">
                        ${repeat(this.fields, (field) => html`
                            <div class="form-group">
                                <label>${field}</label>
                                <input class="form-control" type="text" value="${this.updatedValue[field]}" @change="${(e) => this.updateValue(e, field)}">
                            </div>
                        `)}
                    </div>
                `
            }
            updateParent() {
                const parent = window.parent.frames.list_frame
                const item = {
                    dataId: this.dataId,
                    value: this.updatedValue,
                    index: this.index
                }
                MessageUtility.send(item, parent)
            }
            updateValue(e, field) {
                const value = e.target.value
                this.updatedValue[field] = Number.isInteger(value) ? Number.parseInt(value) : (!isNaN(value) ? Number.parseFloat(value) : value)
                this.updateParent()
            }
        }
        customElements.define('typo3-backend-field-values-editor', FieldValuesEditor)
        return FieldValuesEditor
    })
)