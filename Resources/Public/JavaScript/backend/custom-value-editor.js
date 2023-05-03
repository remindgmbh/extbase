import { html, LitElement } from 'lit';
import { repeat } from 'lit/directives/repeat.js';
import { MessageUtility } from "@typo3/backend/utility/message-utility.js";

class CustomValueEditor extends LitElement {
    updatedValue = {}
    static get properties() {
        return {
            dataId: { type: String },
            props: { type: Array },
            index: { type: Number },
            value: { type: Object }
        }
    }
    firstUpdated() {
        this.updatedValue = this.value ? this.value : this.props.reduce((result, prop) => {
            result[prop.value] = ''
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
                ${repeat(this.props, (prop) => html`
                    <div class="form-group">
                        <label>${prop.label}</label>
                        <input class="form-control" type="text" value="${this.updatedValue[prop.value]}" @change="${(e) => this.updateValue(e, prop)}">
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
    updateValue(e, prop) {
        const value = e.target.value
        this.updatedValue[prop.value] = Number.isInteger(value) ? Number.parseInt(value) : (!isNaN(value) ? Number.parseFloat(value) : value)
        this.updateParent()
    }
}

customElements.define('typo3-backend-custom-value-editor', CustomValueEditor)
