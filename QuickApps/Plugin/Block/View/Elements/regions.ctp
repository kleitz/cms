<?php
$regions = array();

foreach ($blocks_in_theme as &$block) {
    foreach ($block['BlockRegion'] as $key => $blockRegion) {
        if ($blockRegion['theme'] != $theme) {
            unset($block['BlockRegion'][$key]);
        } else {
            $regions[] = $blockRegion['region'];
        }
    }

    $block['BlockRegion'] = array_merge(array(), $block['BlockRegion']);
}

$regions = array_unique($regions);
sort($regions);

foreach ($regions as $region) {
    $blocks_in_region = $blocks_in_region_ids = array();
    $blocks_in_region_ids = Hash::extract($blocks_in_theme, "{n}.BlockRegion.{n}[region={$region}].block_id");

    if (empty($blocks_in_region_ids)) {
        continue;
    }

    foreach ($blocks_in_theme as $_block) {
        if (in_array($_block['Block']['id'], $blocks_in_region_ids)) {
            $blocks_in_region[] = $_block;
        }
    }

    $blocks_in_region = Hash::sort($blocks_in_region, '{n}.BlockRegion.{n}.ordering', 'asc', 'numeric');

    foreach ($blocks_in_region as $key => &$_block) {
        $block_region_id = Hash::extract($_block['BlockRegion'], '{n}.id');
        $_block['Block']['__block_region_id'] = array_pop($block_region_id);
    }

    if (empty($blocks_in_region)) {
        continue;
    }
?>
<h4><?php echo $themes[$theme]['regions'][$region]; ?></h4>
<ul class="sortable">
    <?php foreach ($blocks_in_region as $__block): ?>
    <li class="ui-state-default">
        <input type="hidden" name="data[BlockRegion][<?php echo $theme; ?>][<?php echo $region; ?>][]" value="<?php echo $__block['Block']['__block_region_id']; ?>" />

        <div class="fl">
            <span class="ui-icon ui-icon-arrowthick-2-n-s"></span>
        </div>

        <div class="fl" style="width:60%;">
        <?php
            if ($__block['Block']['title'] == '') {
                if ($__block['Menu']['title'] != '') {
                    echo $__block['Menu']['title'];
                } else {
                    echo "{$__block['Block']['module']}_{$__block['Block']['delta']}";
                }
            } else {
                echo "{$__block['Block']['title']}";
            }

            echo !empty($__block['BlockCustom']['description']) ? " (<em>{$__block['BlockCustom']['description']}</em>)" : '';
        ?>
        </div>

        <div class="fl">
            <?php echo $region; ?>
        </div>

        <div class="fr">
            <a href="<?php echo $this->Html->url("/admin/block/manage/clone/{$__block['Block']['id']}"); ?>" onClick="return confirm('<?php echo __t('Duplicate this block?'); ?>');"><?php echo __t('clone') ?></a> |
            <a href="<?php echo $this->Html->url("/admin/block/manage/edit/{$__block['Block']['id']}"); ?>"><?php echo __t('configure'); ?></a> |
            <?php if ($__block['Block']['module'] == 'Block' || $__block['Block']['clone_of'] != 0) { ?>
                <a href="<?php echo $this->Html->url("/admin/block/manage/delete/{$__block['Block']['id']}"); ?>" onclick="return confirm('<?php echo __t('Delete selected block ?'); ?>');"><?php echo __t('delete'); ?></a> |
            <?php } ?>
        </div>
    </li>
    <?php endforeach; ?>
</ul>
<?php } ?>