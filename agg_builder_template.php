<div id="aggregate-builder-status">
    <div class="agg-builder-error"></div>
    <div class="agg-builder-message"></div>
</div>
<div id="aggregate-graph-overlay">
    <span class="close-icon">x</span>
    <div id="aggregate-img"></div>
    <div id="edit-aggregate-tools">
        <div id="aggregate-type-selector">
            <input type="radio" name="agg-type" value="bits" id="agg-type-bits"><label for="agg-type-bits">bits</label>
            <input type="radio" name="agg-type" value="ucastpkts" id="agg-type-ucastpkts"><label for="agg-type-ucastpkts">ucastpkts</label>
            <input type="radio" name="agg-type" value="errors" id="agg-type-errors"><label for="agg-type-errors">errors</label>
            <input type="radio" name="agg-type" value="mcastpkts" id="agg-type-mcastpkts"><label for="agg-type-mcastpkts">mcastpkts</label>
            <input type="radio" name="agg-type" value="bcastpkts" id="agg-type-bcastpkts"><label for="agg-type-bcastpkts">bcastpkts</label>
        </div>
        <div id="graph-forms"></div>
        <a id="add-aggregate-link">Add another graph</a>
        <label class="meta-field" for="aggregate-title">Graph Title: </label><input id="aggregate-title" type="text" class="meta-field">
        <label class="meta-field" for="aggregate-stack">Stack? </label><input id="aggregate-stack" type="checkbox" class="meta-field" checked="checked">
        <div class="buttons">
            <button id="do-update-aggregate">Update Aggregate</button>
            <button id="do-save-aggregate">Save Aggregate</button>
            <button id="do-reset-aggregate">Reset Aggregate</button>
        </div>
    </div>
</div>