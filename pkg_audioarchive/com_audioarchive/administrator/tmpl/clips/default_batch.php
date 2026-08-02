<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;
?>
<template id="joomla-dialog-audioarchive-batch">
    <div class="p-4 com-audioarchive-batch-dialog">
        <p><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_DESC'); ?></p>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="audioarchive-batch-category"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_CATEGORY_LABEL'); ?></label>
                <select class="form-select" id="audioarchive-batch-category" name="batch[category_id]">
                    <option value="0"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_CATEGORY_NO_CHANGE'); ?></option>
                    <?php foreach ($this->batchCategories as $category) : ?>
                        <option value="<?php echo (int) $category->id; ?>"><?php echo str_repeat('— ', max(0, (int) $category->level - 1)) . $this->escape($category->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <div class="form-check mb-2">
                    <input class="form-check-input" id="audioarchive-batch-apply-tags" type="checkbox" name="batch[apply_tags]" value="1">
                    <label class="form-check-label" for="audioarchive-batch-apply-tags"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_APPLY_TAGS_LABEL'); ?></label>
                </div>
                <label class="form-label" for="audioarchive-batch-tag-mode"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_TAG_MODE_LABEL'); ?></label>
                <select class="form-select mb-3" id="audioarchive-batch-tag-mode" name="batch[tag_mode]">
                    <option value="add"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_TAG_MODE_ADD'); ?></option>
                    <option value="replace"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_TAG_MODE_REPLACE'); ?></option>
                </select>

                <?php if ($this->batchTags) : ?>
                    <fieldset class="com-audioarchive-batch-tags">
                        <legend class="form-label"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_TAGS_LABEL'); ?></legend>
                        <div class="com-audioarchive-batch-tag-search" data-audioarchive-batch-tag-search-wrapper>
                            <label class="visually-hidden" for="audioarchive-batch-tag-search"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_TAG_SEARCH_LABEL'); ?></label>
                            <input
                                class="form-control"
                                id="audioarchive-batch-tag-search"
                                type="search"
                                autocomplete="off"
                                placeholder="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_BATCH_TAG_SEARCH_PLACEHOLDER')); ?>"
                                data-audioarchive-batch-tag-search
                            >
                        </div>
                        <div class="com-audioarchive-batch-tag-options" data-audioarchive-batch-tag-options>
                            <?php foreach ($this->batchTags as $tag) : ?>
                                <label class="form-check com-audioarchive-batch-tag-option" data-audioarchive-batch-tag-option>
                                    <input class="form-check-input" type="checkbox" name="batch[tags][]" value="<?php echo (int) $tag->id; ?>">
                                    <span class="form-check-label"><?php echo $this->escape($tag->title); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="small text-body-secondary mt-2 mb-0" data-audioarchive-batch-tag-no-matches hidden aria-live="polite">
                            <?php echo Text::_('COM_AUDIOARCHIVE_BATCH_TAG_NO_MATCHES'); ?>
                        </p>
                    </fieldset>
                <?php endif; ?>
                <small><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_TAGS_HINT'); ?></small>
            </div>
        </div>

        <div class="border-top mt-4 pt-4">
            <h3 class="h5 mb-2"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_SEARCH_REPLACE_TITLE'); ?></h3>
            <p class="text-body-secondary mb-3"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_SEARCH_REPLACE_DESC'); ?></p>
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="audioarchive-batch-title-search"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_TITLE_SEARCH_LABEL'); ?></label>
                    <input
                        class="form-control"
                        id="audioarchive-batch-title-search"
                        type="text"
                        name="batch[title_search]"
                        autocomplete="off"
                        placeholder="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_BATCH_TITLE_SEARCH_PLACEHOLDER')); ?>"
                        data-audioarchive-batch-title-search
                    >
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label" for="audioarchive-batch-title-replace"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_TITLE_REPLACE_LABEL'); ?></label>
                    <input
                        class="form-control"
                        id="audioarchive-batch-title-replace"
                        type="text"
                        name="batch[title_replace]"
                        autocomplete="off"
                        placeholder="<?php echo $this->escape(Text::_('COM_AUDIOARCHIVE_BATCH_TITLE_REPLACE_PLACEHOLDER')); ?>"
                    >
                </div>
                <div class="col-12 col-md-2">
                    <div class="form-check mb-2">
                        <input
                            class="form-check-input"
                            id="audioarchive-batch-update-alias"
                            type="checkbox"
                            name="batch[update_alias]"
                            value="1"
                            data-audioarchive-batch-update-alias
                            disabled
                        >
                        <label class="form-check-label" for="audioarchive-batch-update-alias"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_UPDATE_ALIAS_LABEL'); ?></label>
                    </div>
                </div>
            </div>
            <small class="text-body-secondary"><?php echo Text::_('COM_AUDIOARCHIVE_BATCH_SEARCH_REPLACE_HINT'); ?></small>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-secondary" data-joomla-dialog-close data-audioarchive-batch-cancel><?php echo Text::_('JCANCEL'); ?></button>
            <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('clips.batch')"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
        </div>
    </div>
</template>
