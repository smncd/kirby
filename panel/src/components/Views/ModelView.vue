<script>
import debounce from "@/helpers/debounce.js";

/**
 * @internal
 */
export default {
	props: {
		changes: Boolean,
		content: Object,
		blueprint: String,
		next: Object,
		prev: Object,
		permissions: {
			type: Object,
			default: () => ({})
		},
		lock: {
			type: [Boolean, Object]
		},
		model: {
			type: Object,
			default: () => ({})
		},
		tab: {
			type: Object,
			default() {
				return {
					columns: []
				};
			}
		},
		tabs: {
			type: Array,
			default: () => []
		}
	},
	computed: {
		id() {
			return this.model.link;
		},
		isLocked() {
			return this.lock?.state === "lock";
		},
		protectedFields() {
			return [];
		}
	},
	data() {
		return {
			hasChanges: this.changes
		};
	},
	watch: {
		changes(value) {
			this.hasChanges = value;
		}
	},
	mounted() {
		this.$events.on("model.reload", this.$reload);
		this.$events.on("keydown.left", this.toPrev);
		this.$events.on("keydown.right", this.toNext);
		this.$events.on("view.save", this.publish);

		this.save = debounce(this.save, 500);
	},
	destroyed() {
		this.$events.off("model.reload", this.$reload);
		this.$events.off("keydown.left", this.toPrev);
		this.$events.off("keydown.right", this.toNext);
		this.$events.off("view.save", this.publish);
	},
	methods: {
		async revert(e) {
			this.$panel.dialog.open({
				component: "k-remove-dialog",
				props: {
					submitButton: {
						icon: "undo",
						text: this.$t("revert")
					},
					text: this.$t("revert.confirm")
				},
				on: {
					submit: async () => {
						await window.panel.post(this.model.link + "/revert");
						this.hasChanges = false;
						this.$view.refresh();
						this.$panel.notification.success();
						this.$panel.dialog.close();
					}
				}
			});
		},
		async save(e) {
			await window.panel.post(this.model.link + "/save", this.content);
		},
		onInput(field, value) {
			this.$set(this.content, field, value);
			this.hasChanges = true;
			this.save();
		},
		onSubmit() {
			this.publish();
		},
		async publish(e) {
			e?.preventDefault();

			await window.panel.post(this.model.link + "/publish", this.content);

			this.$events.emit("model.update");
			this.hasChanges = false;
			this.$panel.view.refresh();
			this.$panel.notification.success();
		},
		toPrev(e) {
			if (this.prev && e.target.localName === "body") {
				this.$go(this.prev.link);
			}
		},
		toNext(e) {
			if (this.next && e.target.localName === "body") {
				this.$go(this.next.link);
			}
		}
	}
};
</script>
