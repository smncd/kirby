<template>
	<k-lab-examples>
		<k-lab-example>
			<k-header :editable="true">
				Title
				<k-button-group slot="buttons">
					<k-button icon="open" size="sm" variant="filled" />
					<k-button icon="cog" size="sm" variant="filled" />
					<k-button
						:icon="isDraft ? 'circle' : 'circle-filled'"
						size="sm"
						variant="filled"
						:text="isDraft ? 'Draft' : 'Public'"
						:theme="isDraft ? 'negative-icon' : 'positive-icon'"
					/>

					<k-form-controls
						:changes="changes"
						:lock="isLocked ? lock : {}"
						preview="https://getkirby.com"
						@discard="log('discard')"
						@submit="log('submit')"
					/>
				</k-button-group>
			</k-header>

			<k-grid
				style="
					--columns: 3;
					--grid-inline-gap: var(--spacing-1);
					--grid-block-gap: var(--spacing-1);
				"
			>
				<k-input
					type="toggle"
					:value="isDraft"
					text="draft"
					@input="isDraft = $event"
				/>
				<k-input
					type="toggle"
					:value="isChanged"
					text="changes"
					@input="isChanged = $event"
				/>
				<k-input
					type="toggle"
					:value="isLocked"
					text="lock"
					@input="isLocked = $event"
				/>
			</k-grid>
		</k-lab-example>

		<k-lab-example label="Unsaved">
			<k-form-controls
				:changes="{ heading: 'This has changed' }"
				@discard="log('discard')"
				@submit="log('submit')"
			/>
		</k-lab-example>
		<k-lab-example label="Locked">
			<k-form-controls
				:lock="lock"
				preview="https://getkirby.com"
				@discard="log('discard')"
				@submit="log('submit')"
			/>
		</k-lab-example>
	</k-lab-examples>
</template>

<script>
export default {
	data() {
		return {
			isDraft: false,
			isLocked: false,
			isChanged: false
		};
	},
	computed: {
		changes() {
			if (this.isChanged) {
				return {
					heading: "This has changed"
				};
			}

			return {};
		},
		lock() {
			return {
				isActive: true,
				modified: "5 minutes ago",
				user: {
					email: "test@getkirby.com"
				}
			};
		}
	},
	methods: {
		log(action) {
			alert(action);
		}
	}
};
</script>

<style>
.k-lab-example .k-header {
	margin-bottom: 2rem;
}
</style>
