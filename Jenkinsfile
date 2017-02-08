elifePipeline {
    def commit
    stage 'Checkout', {
        checkout scm
        commit = elifeGitRevision()
    }

    stage 'Project tests', {
        lock('recommendations--ci') {
            builderDeployRevision 'recommendations--ci', commit
            builderProjectTests 'recommendations--ci', '/srv/recommendations', ['/srv/recommendations/build/phpunit.xml']
        }
    }

    elifeMainlineOnly {
        stage 'End2end tests', {
            elifeEnd2EndTest({
                builderDeployRevision 'recommendations--end2end', commit
                builderSmokeTests 'recommendations--end2end', '/srv/recommendations'
            }, 'recommendations')
        }

        stage 'Approval', {
            elifeGitMoveToBranch commit, 'approved'
        }
    }
}
