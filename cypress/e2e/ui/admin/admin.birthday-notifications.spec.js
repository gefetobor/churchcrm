/// <reference types="cypress" />

// Birthday Notifications settings (config IDs 2096-2100)
describe("Admin Settings - Birthday Notifications", () => {
    const templateField = 'textarea[name="new_value[2100]"]';

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("shows the Birthday Notifications group with its settings", () => {
        cy.visit("SystemSettings.php");
        cy.contains("Birthday Notifications");
        cy.contains("Birthday Greetings"); // existing group still present
        cy.get('select[name="new_value[2096]"]').should("exist");
        cy.get('select[name="new_value[2097]"]').should("exist");
        cy.get('textarea[name="new_value[2098]"]').should("exist");
        cy.get('input[name="new_value[2099]"]').should("exist");
        cy.get(templateField).should("exist");
    });

    it("ships with a default HTML template that loops over people", () => {
        cy.visit("SystemSettings.php");
        cy.get(templateField)
            .invoke("val")
            .should("contain", "{% for person in people %}")
            .and("contain", "{{ person.fullName }}")
            .and("contain", "{% endfor %}");
    });

    it("keeps the Twig tokens and recipients after saving", () => {
        cy.visit("SystemSettings.php");
        cy.get('textarea[name="new_value[2098]"]').clear({ force: true }).type("gifts@example.com, pastor@example.com", { force: true });
        cy.get("#save").click();

        cy.visit("SystemSettings.php");
        cy.get('textarea[name="new_value[2098]"]').should("have.value", "gifts@example.com, pastor@example.com");
        cy.get(templateField)
            .invoke("val")
            .should("contain", "{% for person in people %}")
            .and("contain", "{% if person.age %}")
            .and("contain", "{% endfor %}");

        // restore default (empty) recipient list
        cy.get('textarea[name="new_value[2098]"]').clear({ force: true });
        cy.get("#save").click();
    });
});
