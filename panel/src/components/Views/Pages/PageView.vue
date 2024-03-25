<template>
	<k-panel-inside
		:data-has-tabs="tabs.length > 1"
		:data-id="model.id"
		:data-locked="isLocked"
		:data-template="blueprint"
		class="k-page-view"
	>
		<template #topbar>
			<k-prev-next v-if="model.id" :prev="prev" :next="next" />
		</template>

		<k-header
			:editable="permissions.changeTitle && !isLocked"
			class="k-page-view-header"
			@edit="$dialog(id + '/changeTitle')"
		>
			{{ model.title }}
			<template #buttons>
				<k-button-group>
					<k-button
						v-if="permissions.preview && model.previewUrl"
						:link="model.previewUrl"
						:title="$t('open')"
						icon="open"
						target="_blank"
						variant="filled"
						size="sm"
						class="k-page-view-preview"
					/>

					<k-button
						:disabled="isLocked === true"
						:dropdown="true"
						:title="$t('settings')"
						icon="cog"
						variant="filled"
						size="sm"
						class="k-page-view-options"
						@click="$refs.settings.toggle()"
					/>
					<k-dropdown-content
						ref="settings"
						:options="$dropdown(id)"
						align-x="end"
					/>

					<k-languages-dropdown />

					<k-button
						v-if="status && !hasChanges"
						v-bind="statusBtn"
						class="k-page-view-status"
						variant="filled"
						@click="$dialog(id + '/changeStatus')"
					/>
				</k-button-group>

				<template v-if="hasChanges === true">
					<k-button-group layout="collapsed">
						<k-button
							icon="edit"
							size="sm"
							theme="notice"
							text="Publish changes"
							variant="filled"
							@click="publish"
						/>
						<k-button
							icon="dots"
							size="sm"
							theme="notice"
							variant="filled"
							@click="$refs.changes.toggle()"
						/>
					</k-button-group>
					<k-dropdown-content ref="changes" align-x="right">
						<k-dropdown-item
							:link="model.previewUrl + '?changes=true'"
							icon="open"
							target="_blank"
						>
							Preview changes
						</k-dropdown-item>
						<k-dropdown-item icon="preview">Compare changes</k-dropdown-item>
						<hr />
						<k-dropdown-item icon="undo" @click="revert">
							Revert Changes
						</k-dropdown-item>
					</k-dropdown-content>
				</template>
			</template>
		</k-header>

		<k-model-tabs :tab="tab.name" :tabs="tabs" />

		<k-sections
			:blueprint="blueprint"
			:content="content"
			:empty="$t('page.blueprint', { blueprint: $esc(blueprint) })"
			:lock="lock"
			:parent="id"
			:tab="tab"
			@input="onInput"
			@submit="onSubmit"
		/>
	</k-panel-inside>
</template>

<script>
import ModelView from "../ModelView.vue";

export default {
	extends: ModelView,
	props: {
		status: Object
	},
	computed: {
		protectedFields() {
			return ["title"];
		},
		statusBtn() {
			return {
				...this.$helper.page.status.call(
					this,
					this.model.status,
					!this.permissions.changeStatus || this.isLocked
				),
				responsive: true,
				size: "sm",
				text: this.status.label,
				variant: "filled"
			};
		}
	}
};
</script>

<style>
/** TODO: .k-page-view:has(.k-tabs) .k-page-view-header */
.k-page-view[data-has-tabs="true"] .k-page-view-header {
	margin-bottom: 0;
}

.k-page-view-status {
	--button-color-back: var(--color-gray-300);
	--button-color-icon: var(--theme-color-600);
	--button-color-text: initial;
}
</style>
