// / <reference types="Cypress" />

import ProductPageObject from "../../../../support/pages/module/sw-product.page-object";

const page = new ProductPageObject();

describe('SDK Tests: Notification', ()=> {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            })
            .then(() => {
                cy.onlyOnFeature('FEATURE_NEXT_17950');

                cy.get('.sw-dashboard-statistics__card-headline')
                    .should('be.visible');

                cy.get('.sw-loader').should('not.exist');
                cy.get('.sw-skeleton').should('not.exist');

                cy.getSDKiFrame('sw-main-hidden')
                    .should('exist');
            })
    });

    it('@sdk: dispatch a notification', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.log('Go to extension page')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Trigger a notification')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Dispatch a notification')
            .click();

        cy.get('.sw-alert__title')
            .contains('Your title')

        cy.get('.sw-alert__message')
            .contains('Your message')
    })
})
